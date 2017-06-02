<?php
/**
 * This file is part of the localGoogle project
 *
 * Copyright (c) 2017, Sochima Biereagu
 * Under MIT License
 */

/**
 * helpers functions used across localGoogle
 */

/*   search.php  */

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

// result page-content to display in search results page
// highlighting words found in the search query
function getDisplayContent($content, $query)
{
    $parts = explode(".", $content);
    $words = explode(" ", $query);

    $r = '';

    foreach ($parts as $sntnc) {
        foreach ($words as $w) {
            if (preg_match("/\b".escape_regex($w)."\b/i", $sntnc, $m)) {
                $m = $m[0];

                $sntnc = str_replace("$m", "<b>$m</b>", $sntnc); // highlight keyword
                $r = $sntnc . "$r."; // prefix important part
            } elseif (count($parts)) {
                $r .= $sntnc; // append less important
            }
        }
    }

    return $r;
}


// pagination
function displayPaging($totalRows)
{
    global $startAt;

    $pages = round($totalRows / 10);
    $curpage = $startAt/10 + 1;

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

function getPageContent($url)
{
    return ($cnt = @file_get_contents($url)) ? $cnt : 0;
}

// validates the $url and makes sure the $name isnt empty
function isInvalid($name, $url)
{
    return empty($name) || empty($url)
        || !getPageContent($url);
}

/* crawl.php, sites.php */

// convert seconds to <seconds>(seconds|minutes|hours)
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


/* setup_database.php, localgoogle/bin */

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
            'DB_NAME' => 'localgoogle'
        ];
    
        file_put_contents($config_file, json_encode($data, JSON_PRETTY_PRINT));
    }
}

