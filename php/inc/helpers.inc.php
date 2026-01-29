<?php
/**
 * This file is part of the localGoogoo project
 *
 * Copyright (c) 2017, Sochima Biereagu
 * Under MIT License
 */

/**
 * helpers functions used across localGoogoo
 */

/*   search/results_display.inc.php  */

// adds backslash to regex meta characters in a string
function escape_regex($r)
{
    $p = ['^', '$', '.', '/', '\\', '[', ']', '|', '(', ')', '?', '*', '+', '{', '}'];
    $nr = ""; // new $r

    for ($i=0; $i<strlen($r); $i+=1) {
        $c = $r[$i];

        if (in_array($c, $p)) {
            $nr .= "\\".$c;
        } else {
            $nr .= $c;
        }
    }

    return $nr;
}


/*   search.php  */

// pagination
function displayPaging($totalRows)
{
    global $startAt;
    $results_per_page = 10;

    $pages = round($totalRows / $results_per_page);
    $curpage = $startAt/$results_per_page + 1;

    echo "<div style='text-align: center;' class='center'>"; // center pagination elm

    if ($startAt > 0) {
        echo _link("Prev", "start=".($curpage-1));
    }

    for ($x = max(1, $curpage - 5); $x <= min($curpage + 5, $pages); $x += 1) {
        echo _link("$x", "start=".$x, $x === $curpage);
    }

    if ($curpage+1 <= $pages) {
        echo _link("Next", "start=".($curpage+1));
    }

    echo "</div>";
}


function _link($name, $start, $isCurrent=false)
{
    global $query;

    echo $isCurrent
     ? "<span style='display:inline-block; margin-left: 10px;'>$name</span>"
     : " <a style='display: inline-block;margin-left: 10px;' href=?q=".urlencode($query)."&$start> $name </a> ";
}


/* crawl.php, start_crawler.php */

/**
 * Resolves a hostname to a list of IPs (IPv4 and IPv6)
 * @param string $host
 * @return array
 */
function resolveHostname($host) {
    $ips = [];

    // Check if host is already an IP
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return [$host];
    }

    // Try DNS_A (IPv4)
    $r4 = dns_get_record($host, DNS_A);
    if ($r4) {
        foreach ($r4 as $r) {
            if (isset($r['ip'])) $ips[] = $r['ip'];
        }
    }

    // Try DNS_AAAA (IPv6)
    $r6 = dns_get_record($host, DNS_AAAA);
    if ($r6) {
        foreach ($r6 as $r) {
            if (isset($r['ipv6'])) $ips[] = $r['ipv6'];
        }
    }

    // Fallback/Supplement with gethostbyname (handles /etc/hosts for localhost)
    $ip_fallback = gethostbyname($host);
    if ($ip_fallback !== $host && !in_array($ip_fallback, $ips)) {
        $ips[] = $ip_fallback;
    }

    return $ips;
}

/**
 * Checks if an IP is blocked (e.g., localhost not in allowlist)
 * @param string $ip
 * @param string $originalUrl
 * @return bool true if blocked, false if allowed
 */
function isIpBlocked($ip, $originalUrl) {
    $isLocal = false;

    // IPv4 Loopback (127.0.0.0/8) or 0.0.0.0 (Any)
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        if (strpos($ip, '127.') === 0 || $ip === '0.0.0.0') {
            $isLocal = true;
        }
    }
    // IPv6 Loopback (::1) or Unspecified (::)
    elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // Expand IPv6 to full format for easier comparison if needed,
        // but simple checks for standard string representations work for exact matches.
        // ::1, 0:0:0:0:0:0:0:1, etc.
        // inet_pton can normalize.
        $packed = inet_pton($ip);
        if ($packed !== false) {
             // ::1
             if ($packed === inet_pton('::1')) {
                 $isLocal = true;
             }
             // :: (Unspecified)
             elseif ($packed === inet_pton('::')) {
                 $isLocal = true;
             }
             // IPv4-mapped IPv6 (::ffff:127.0.0.1)
             elseif (strlen($packed) === 16) {
                 // Check if it's an IPv4 mapped address (first 80 bits 0, next 16 bits 1 (ffff))
                 // Actually the prefix is ::ffff:0:0/96 usually.
                 // Easier: check string if it contains . and :
                 if (strpos($ip, '.') !== false && strpos($ip, ':') !== false) {
                     // Extract last part
                     $parts = explode(':', $ip);
                     $last = end($parts);
                     if (filter_var($last, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                         if (strpos($last, '127.') === 0 || $last === '0.0.0.0') {
                             $isLocal = true;
                         }
                     }
                 }
             }
        }
    }

    if ($isLocal) {
        static $configCache = null;
        static $lastMtime = 0;
        $configPath = __DIR__ . '/../../config.json';

        if (file_exists($configPath)) {
            $mtime = filemtime($configPath);
            if ($configCache === null || $mtime > $lastMtime) {
                $configCache = json_decode(file_get_contents($configPath), true);
                $lastMtime = $mtime;
            }
        } else {
            $configCache = [];
        }

        $allowed = $configCache['ALLOWED_LOCALHOST_PATTERNS'] ?? [];

        if (in_array('*', $allowed)) {
            return false; // Allowed
        }

        foreach ($allowed as $pattern) {
            if (fnmatch($pattern, $originalUrl)) {
                return false; // Allowed
            }
        }
        return true; // Blocked
    }

    return false; // Not local, allowed
}

/**
 * Safely fetches URL content preventing SSRF/DNS Rebinding
 * @param string $url
 * @return string|false
 */
function safeGetUrlContent($url) {
    $max_redirects = 5;
    $attempts = 0;

    while ($attempts < $max_redirects) {
        $attempts++;

        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme'])) return false;

        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['http', 'https'])) return false;

        $host = $parsed['host'];
        $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);

        // Resolve Host
        $ips = resolveHostname($host);
        if (empty($ips)) return false;

        $selected_ip = $ips[0]; // Pick first resolved IP

        // Validate IP
        if (isIpBlocked($selected_ip, $url)) {
            return false;
        }

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Handle manually
        curl_setopt($ch, CURLOPT_HEADER, true); // We need headers for redirects
        // Pin DNS
        curl_setopt($ch, CURLOPT_RESOLVE, ["$host:$port:$selected_ip"]);

        // Timeout to prevent hanging
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($response === false) return false;

        // Handle Redirects (3xx)
        if ($http_code >= 300 && $http_code < 400) {
            $header = substr($response, 0, $header_size);
            if (preg_match('/^Location:\s*(.*)$/mi', $header, $matches)) {
                $new_loc = trim($matches[1]);

                // Handle relative URL
                $url_parts = parse_url($url);
                $base_url = $url_parts['scheme'] . '://' . $url_parts['host'] . (isset($url_parts['port']) ? ':' . $url_parts['port'] : '');

                // Simple relative to absolute conversion (can reuse class method if available, but keep standalone here)
                if (parse_url($new_loc, PHP_URL_SCHEME) == '') {
                    if ($new_loc[0] === '/') {
                        $url = $base_url . $new_loc;
                    } else {
                        // Relative path... simplified handling:
                         $path = $url_parts['path'] ?? '/';
                         $path = preg_replace('#/[^/]*$#', '', $path);
                         $url = $base_url . $path . '/' . $new_loc;
                    }
                } else {
                    $url = $new_loc;
                }
                continue; // Next attempt
            }
            return false; // Redirect without location?
        }

        if ($http_code == 200) {
            return substr($response, $header_size);
        }

        return false;
    }

    return false; // Too many redirects
}

function getPageContent($url)
{
    return ($cnt = safeGetUrlContent($url)) ? $cnt : 0;
}

// validates the $url and makes sure the $name isnt empty
function isInvalid($name, $url)
{
    return empty($name) || empty($url)
        || !getPageContent($url);
}

/* crawl.php, sites.php */

// convert seconds to (seconds|minutes|hours)
function secToTime($s)
{
    if ($s === "incomplete") {
        return "<b> Incomplete! </b>";
    }

    $s = (int) $s;

    if ($s < 60) {
        return "$s second(s)";
    } elseif ($s <= 3600) {
        return (round($s / 60))." minute(s)";
    } else {
        return (round($s / 3600))." hour(s)";
    }
}

/* crawl.php */

// echo texts that stay on a single line
function progress($t)
{
    return sprintf("%s\r", $t);
}

/* crawler.class.php */

function hasKey($arr, $key)
{
    // checks if $arr has index $key, returns the value if true
    // else returns false
    return array_key_exists($key, $arr) ? $arr[$key] : false;
}


/* setup_database.php, localgoogoo/bin */

function prepareConfigFile($config_file)
{
    if (!file_exists($config_file) || !json_decode(file_get_contents($config_file))) {
        // user may have deleted/corrupted the config file
        // so we create a new one with default data

        if (file_exists($config_file)) {
            // store old contents in config.old.json
            file_put_contents(str_replace(".json", ".old.json", $config_file), file_get_contents($config_file));
        }

        $data =  [
            'DB_HOST' => 'localhost',
            'DB_USER' => 'root',
            'DB_PASSWORD' => '',
            'DB_NAME' => 'localgoogoo',
            'ALLOWED_LOCALHOST_PATTERNS' => ['*']
        ];
    
        file_put_contents($config_file, json_encode($data, JSON_PRETTY_PRINT));
    }
}
