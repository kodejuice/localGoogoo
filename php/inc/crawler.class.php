<?php
/**
 * This file is part of the localGoogle project
 *
 * Copyright (c) 2017, Sochima Biereagu
 * Under MIT License
 */


/**
 * localgoogle web cralwer
 *
 * crawls and indexes a website
 */

require_once __DIR__."/../lib/simple_html_dom.php";
require_once __DIR__."/helpers.inc.php";

class LGCrawler
{
    private $siteurl;
    private $sitename;
    private $SQLConn;

    public $crawledPages = []; // holds all links crawled in all webpages
    public $tooLarge = false;
    public $logFile = "./../log.txt";

    private $onCompleteCallback = [];
    private $onCrawlCallback = [];

    private $lastIndexedURL;

    /**
     * Crawler constructor
     * 
     * @param [string]   $sitename website name
     * @param [string]   $siteurl  website url
     * @param [resource] $SQLConn  mysql connection
     */
    public function __construct($sitename, $siteurl, $SQLConn)
    {
        $this->sitename = $SQLConn->escape_string($sitename);
        $this->siteurl =  $SQLConn->escape_string($siteurl);

        $this->SQLConn = $SQLConn;

        // insert website details into database before we start crawling
        // if details exist, get last indexed url
        $this->insertSiteInToDB();
    }

    /**
     * method to log messages
     * 
     * @param string $file the log file name
     * @param string $text the log message
     */
    public function log($file, $text)
    {
        system("echo $text>>$file");
    }

    /**
     * complete callback
     *
     * @param [function] $value [callback function called on crawl-complete]
     */
    public function onComplete($cb)
    {
        $this->onCompleteCallback[] = $cb;
    }

    /**
     * Initiate crawler
     */
    public function startCrawler($cb = null)
    {
        if ($cb) {
            $this -> onCrawlCallback[] = $cb;
        }

        $start = time();

        $this->_startCrawler($this->lastIndexedURL);

        // crawl complete
        $this->onCompleteCallback[0](time() - $start);
    }

    /**
     * Crawler Method,
     *  crawls the given url and adds the pages to the database
     */
    private function _startCrawler($url = null)
    {
        $url = (!$url)? $this->siteurl :$url;

        $crawledPages = &$this->crawledPages;

        // callback
        if (isset($this->onCrawlCallback[0])) {
            $this->onCrawlCallback[0]();
        }

        // remove queries
        extract(parse_url($url));
        $path = isset($path) ? $path : "/";
        $url = strtolower("$scheme://$host$path");

        // if url is not 200 or isn't html, dont crawl
        if (!($fileContent = $this->getPageContent($url)) || !$this->isHTML($fileContent) || in_array($url, $crawledPages)) {
            return;
        }

        // add `$url` to database first,
        // before we crawl the links in its content
        if (!in_array($url, $crawledPages)) {
            $this->addPageToDatabase($url, $fileContent);
            array_push($crawledPages, $url);
        }

        // will hold all links in the the current page
        $links = [];

        // get all links in `$url`s content
        $this->getLinks(
            $url, function ($pageURL) use ($fileContent, &$links, &$crawledPages, $url) {

                // callback
                if (isset($this->onCrawlCallback[0])) {
                    $this->onCrawlCallback[0]();
                }

                // remove queries from url
                extract(parse_url($pageURL));
                $path = isset($path) ? $path : "/";
                $pageURL = strtolower("$scheme://$host$path");

                if (!in_array($pageURL, $crawledPages)) {
                
                    // only add link/page to the database if its (not an external link {different host}, valid and is html)
                    if ($this->isAlike($this->siteurl, $pageURL) && ($content = $this->getPageContent($pageURL))
                        && $this->isHTML($content)
                    ) {
                        $this->addPageToDatabase($pageURL, $content);
                        array_push($crawledPages, $pageURL); // add to crawled-pages list, prevent re-crawling

                        // get links from this webpage and push it to the '$links' array for later crawling
                        $this->getLinks(
                            $pageURL, function ($link) use (&$links, &$crawledPages, $url) {

                                // callback
                                if (isset($this->onCrawlCallback[0])) {
                                    $this->onCrawlCallback[0]();
                                }

                                // remove queries from url
                                extract(parse_url($link));
                                $path = isset($path) ? $path : "/";
                                $link = strtolower("$scheme://$host$path");

                                // only push link if its not crawled already,
                                // and its not an external link
                                if ($this->isAlike($this->siteurl, $link) && !in_array($link, $crawledPages)) {
                                    array_push($links, $link);
                                }
                            }, $content  /* url content provided so as to reduce execution time */
                        );
                    }
                }
            }, $fileContent /* url content provided so as to reduce execution time */
        );

        // callback
        if (isset($this->onCrawlCallback[0])) {
            $this->onCrawlCallback[0]();
        }

        // recurse
        // crawl all links in the `$links` array
        foreach ($links as $link) {
            if (!in_array($link, $crawledPages)) {
                $this->_startCrawler(strtolower($link));
            }
        }
    }

    /**
     * convert relative url to absolute url
     * 
     * @param [string] $rel  relative url
     * @param [string] $base base url
     * 
     * @return [string]        absolute url
     */
    private function rel2abs($rel, $base)
    {
        // http://stackoverflow.com/questions/4444475/transfrom-relative-path-into-absolute-url-using-php
        
        if (empty($rel)) {
            return $base.$rel;
        }

        /* return if already absolute URL */
        if (parse_url($rel, PHP_URL_SCHEME) != '') {
            return($rel);
        }

        /* queries and anchors */
        if ($rel[0]=='#' || $rel[0]=='?') {
            return($base.$rel);
        }

        /* parse base URL and convert to local variables:
           $scheme, $host, $path */
        extract(parse_url($base));

        /* remove non-directory element from path */
        $path = preg_replace('#/[^/]*$#', '', $path);

        /* destroy path if relative url points to root */
        if ($rel[0] == '/') {
            $path = '';
        }

        /* dirty absolute URL */
        $abs = '';

        /* do we have a user in our URL? */
        if (isset($user)) {
            $abs.= $user;

            /* password too? */
            if (isset($pass)) {
                $abs.= ':'.$pass;
            }

            $abs.= '@';
        }

        $abs.= $host;

        /* did somebody sneak in a port? */
        if (isset($port)) {
            $abs.= ':'.$port;
        }

        $abs.=$path.'/'.$rel;

        /* replace '//' or '/./' or '/foo/../' with '/' */
        $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
        for ($n=1; $n>0; $abs=preg_replace($re, '/', $abs, -1, $n)) {
        }

        /* absolute URL is ready! */
        return($scheme.'://'.$abs);
    }

    /**
     * get all links in a webpage
     *
     * @param [string]   $u        URL, we fetch its contents and get all links from the page
     * @param [callback] $callback callback called on every link found
     * @param [string]   $content  html content provided so as to mitigate the call of the `file_get_html` function
     */
    private function getLinks($u, $callback, $content = '')
    {
        $found_urls = [];

        $html = ($content !== '') ? str_get_html($content) : file_get_html($u);
        
        // check if the html object has the 'find' method,
        // if it doesn't (false was returned) then the html content couldn't be parsed (too large)
        // see the 'lib/simple_html_dom.php' script, line 91
        if (!is_callable([$html, "find"], true)) {
            $this->tooLarge = true;
            return;
        }

        foreach ($html->find("a") as $a) {
            $url = $this->rel2abs($a->href, $u);
            $enurl = urlencode($url);
            
            if (!empty($url) && !array_key_exists($enurl, $found_urls) && $this->isAlike($this->siteurl, $url)) {
                $found_urls[$enurl] = 1;
                $callback($url);
            }
        }
    }

    /**
     * method to add pages to the db as we crawl
     * 
     * @param [string] $link    page url
     * @param [string] $content page content
     * 
     * @return [boolean]        page added or not
     */
    private function addPageToDatabase($link, $content)
    {
        $name = $this->sitename;
        $conn = $this->SQLConn;

        $dom = str_get_html($content);

        // check if the html object has the 'find' method,
        // if it doesn't (false was returned) then the html content couldn't be parsed (too large)
        // see the 'lib/simple_html_dom.php' script line 91
        if (!is_callable([$dom, "find"], true)) {
            $this->tooLarge = true;
            return;
        }

        $pageTitle = isset($dom->find("title")[0]) ? $dom -> find("title")[0] -> innertext() : "";
        $pageTitle = $conn -> escape_string($pageTitle);

        $content = isset($dom -> find("body")[0]) ?  $dom -> find("body")[0]->innertext() : $content;

        $content = htmlspecialchars_decode($content); // decode html entities
        $content = $this->stripTags($content); // strip out tags
        $content = $conn -> escape_string($this->_trim($content));

        $link = $conn -> escape_string($link);

        $sql = <<<sql
        INSERT INTO pages (page_website, page_url, page_title, page_content)
        VALUES ('$name', '$link', '$pageTitle', '$content');
sql;

        @$conn->query($sql);


        // update info in the `website` table
        $linksCount = (int) $conn -> query("SELECT COUNT(*) FROM pages WHERE page_website='$name'") -> fetch_row()[0];
        $date = date("jS F Y - l h:i:s A");

        $updateWebsiteInfo = <<<sql
            UPDATE websites
            SET pages_count='$linksCount', last_index_date='$date', last_indexed_url='$link'
            WHERE site_name='$name';
sql;

        if (!$conn->query($updateWebsiteInfo)) {
            $msg = "Failed to update pages count";
            $this->log($this->logFile, "- $msg");
            
            echo PHP_EOL.$msg;
        }

        return true;
    }

    /**
     * insert website details into database,
     * before crawling begins
     */
    private function insertSiteInToDB()
    {
        $conn = $this->SQLConn;

        $name = $this->sitename;
        $url = $this->siteurl;

        // check if this website exists in the database first
        $site_exists = $conn->query("SELECT COUNT(*) FROM websites WHERE site_name='$name'");

        if ((int) $site_exists -> fetch_row()[0] === 0) {
            // site doesn't exist, insert it into the db

            $data = "INSERT INTO websites (site_url, site_name, pages_count, last_index_date, last_indexed_url, crawl_time)
            VALUES ('$url', '$name', 0, '".date("jS F Y - l h:i:s A")."', '$url', 'incomplete');";

            if (!$conn->query($data)) {
                $msg = "Failed to crawl website, could not insert data into the database - ".$conn->error;
                $this->log($this->logFile, "- $msg");

                exit(PHP_EOL.$msg);
            }
        } else {
            // else get last indexed url of this site from the database
            // so we continue where we stopped
            $last_indexed_url = $conn->query("SELECT last_indexed_url FROM websites WHERE site_name='$name'")
                ->fetch_row()[0];

            $this->lastIndexedURL = ($u = $last_indexed_url)? $u : $url;
        }
    }

    /**
     * strip out tags from html document
     * + (<script> with its contents)
     * 
     * @param [string] $string HTML string
     * 
     * @return [string]          HTML
     */
    private function stripTags($string)
    {
        $string = preg_replace("#<script[^>]*>.*</script>#s", "", $string);

        // remove htmlsentities
        $string = preg_replace("#&[a-z]+;#", "", $string);

        return strip_tags($string);
    }

    /**
     * check if string is html
     *
     * @param [string] $string string to check
     * 
     * @return [boolean]         html or not
     */
    private function isHTML($string)
    {
        return preg_match("/<html.*/i", $string) && preg_match("/<body.*/i", $string);
    }

    /**
     * check if urls are alike
     * so as to prevent the crawler from exceeding its boundaries
     * 
     * @param [string] $url1 original url
     * @param [string] $url2 test url
     * 
     * @return [boolean]       alike or not
     */
    private function isAlike($url, $testUrl)
    {
        // make sure $testUrl is a superset of $url

        $u1 = parse_url(strtolower($url));
        $u2 = parse_url(strtolower($testUrl));

        if (!hasKey($u1, "path") || !hasKey($u2, "path")) {
            return false;
        }

        $u1Path = explode("/", $u1['path']);
        $u2Path = explode("/", $u2['path']);

        // remove html filenames from url
        $filename = $u1Path[count($u1Path) - 1];
        if (preg_match("/\.(\w+)/", $filename) || empty($filename)) {
            array_pop($u1Path);
        }

        $lastIndexOfURL = count($u1Path) - 1;
        return (hasKey($u1, "host") === hasKey($u2, "host")
            && $u1Path[$lastIndexOfURL] === hasKey($u2Path, $lastIndexOfURL))
            || $url === $testUrl;
    }

    /**
     * Takes a url and returns false (if its inaccessible) else it contents
     * 
     * @param [string] $url url to fetch
     */
    private function getPageContent($url)
    {
        return ($cnt = @file_get_contents($url)) ? $cnt : false;
    }

    /**
     * delete multiple whitespaces
     *
     * @param [string] $value string to trim
     * 
     * @return [string]        trimmed string
     */
    private function _trim($str)
    {
        return trim(preg_replace("/\s+/", " ", $str));
    }
}
