<?php

declare(strict_types=1);

/**
 * This file is part of the localGoogoo project
 *
 * Copyright (c) 2017, Sochima Biereagu
 * Under MIT License
 */


/**
 * localgoogoo web cralwer
 *
 * crawls and indexes a website
 */

require_once __DIR__ . "/../lib/simple_html_dom.php";
require_once __DIR__ . "/../inc/helpers.inc.php";

class LGCrawler
{
    private $siteurl;
    private $sitename;
    private $SQLConn;

    /**
     * holds all links crawled in all webpages
     * (associative array)
     * 
     * url => boolean
     * 
     * @var array
     */
    public $crawledPages = [];

    /**
     * Will store all pages already in DB, to prevent double entry useful for when
     *  the crawling process is restarted, so we prevent cralwed pages to be added again
     *
     * url => boolean
     *
     * @var array
     */
    public readonly array $crawledPagesInDB;

    /**
     * Will hold the links for pages crawled
     * (associative array)
     * 
     * url => array
     *
     * @var array
     */
    private array $pageLinks = [];

    /**
     * Map page urls to its html content
     *
     * url => string
     *
     * @var array
     */
    private array $pageContentCache = [];

    /**
     * Crawled pages count for current site
     *
     * @var integer
     */
    public int $crawledPagesCount = 0;

    public $tooLarge = false;
    public $logFile = __DIR__ . "/../../log.txt";

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
        // if details exist, get last indexed url ($this->lastIndexedURL)
        $this->insertSiteInToDB();

        // get all crawledPages in DB
        $this->crawledPagesInDB = $this->getAllCrawledURLs();
        $this->crawledPagesCount = count($this->crawledPagesInDB);
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
            $this->onCrawlCallback[] = $cb;
        }

        $start = time();

        // start from last indexed URL, this will
        // speed up things if this isn't the first time
        $this->runCrawler($this->lastIndexedURL);

        if ($this->lastIndexedURL !== $this->siteurl) {
            // the lastIndexedURL may be a leaf node
            // so just run this also, if there are no new pages add then nothing happens
            $this->runCrawler($this->siteurl);
        }

        // crawl complete
        $this->updateSiteStats(null, $this->crawledPagesCount);
        $this->onCompleteCallback[0](time() - $start);
    }

    /**
     * Crawler Method,
     *  crawls the given url and adds the pages to the database
     *  recusively calls itself on all links found on a page
     */
    private function runCrawler($url = null)
    {
        $url = (!$url) ? $this->siteurl : $url;

        $crawledPages = &$this->crawledPages;

        // callback
        if (isset($this->onCrawlCallback[0])) {
            $this->onCrawlCallback[0]();
        }

        // remove queries
        extract(parse_url($url));
        $path = isset($path) ? $path : "/";
        $url = ("$scheme://$host$path");

        // if (url is not 200) or (page isn't html) or (page is already cralwed), { dont crawl }
        if (!($fileContent = $this->getPageContent($url)) || !$this->isHTML($fileContent) || array_key_exists($url, $crawledPages)) {
            return;
        }

        // add current page to database,
        if (!array_key_exists($url, $crawledPages)) {
            if (!array_key_exists($url, $this->crawledPagesInDB)) {
                $this->addPageToDatabase($url, $fileContent);
            }
            $crawledPages[$url] = 1;
        }

        // will hold all links in the the current page
        $links = [];

        // store all links in `$url`s content
        //  for later crawling
        $this->getLinks(
            $url,
            function ($pageURL) use ($fileContent, &$links, &$crawledPages, $url) {

                // callback
                if (isset($this->onCrawlCallback[0])) {
                    $this->onCrawlCallback[0]();
                }

                // remove queries from url
                extract(parse_url($pageURL));
                $path = isset($path) ? $path : "/";
                $pageURL = ("$scheme://$host$path");

                if ($this->isAlike($this->siteurl, $pageURL) && !array_key_exists($pageURL, $crawledPages)) {
                    array_push($links, $pageURL);
                }
            },
            $fileContent /* page content provided so this method wont need to fetch the content again */
        );

        // crawl all links in the `$links` array
        foreach ($links as $link) {
            if (!array_key_exists($link, $crawledPages)) {
                $this->runCrawler($link);
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
            return $base . $rel;
        }

        /* return if already absolute URL */
        if (parse_url($rel, PHP_URL_SCHEME) != '') {
            return ($rel);
        }

        /* queries and anchors */
        if ($rel[0] == '#' || $rel[0] == '?') {
            return ($base . $rel);
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
            $abs .= $user;

            /* password too? */
            if (isset($pass)) {
                $abs .= ':' . $pass;
            }

            $abs .= '@';
        }

        $abs .= $host;

        /* did somebody sneak in a port? */
        if (isset($port)) {
            $abs .= ':' . $port;
        }

        $abs .= $path . '/' . $rel;

        /* replace '//' or '/./' or '/foo/../' with '/' */
        $re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
        for ($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n)) {
        }

        /* absolute URL is ready! */
        return ($scheme . '://' . $abs);
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
        if (array_key_exists($u, $this->pageLinks)) {
            array_map($callback, $this->pageLinks);
            return;
        }

        $found_urls = [$u => 1];
        $html = ($content !== '') ? str_get_html($content) : file_get_html($u);

        // check if the html object has the 'find' method,
        // if it doesn't (false was returned) then the html content couldn't be parsed (too large)
        // see the 'lib/simple_html_dom.php' script, line 91
        if (!is_callable([$html, "find"], true)) {
            $this->tooLarge = true;
            return;
        }

        $urls = [];
        foreach ($html->find("a") as $a) {
            $url = $this->rel2abs($a->href, $u);
            $enurl = urlencode($url);

            if (!empty($url) && !array_key_exists($enurl, $found_urls) && $this->isAlike($this->siteurl, $url)) {
                $found_urls[$enurl] = 1;
                $callback($url);
                array_push($urls, $url);
            }
        }
        $this->pageLinks[$u] = $urls;
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
        // see the 'lib/simple_html_dom.php' script line 113
        if (!is_callable([$dom, "find"], true)) {
            $this->tooLarge = true;
            return;
        }

        $pageTitle = isset($dom->find("title")[0]) ? $dom->find("title")[0]->innertext() : "";
        $pageTitle = $conn->escape_string($pageTitle);

        // get <body> tag from page content
        $content = isset($dom->find("body")[0])
            ?  $dom->find("body")[0]->innertext()
            : $content;

        // escape strings
        $content = $conn->escape_string($this->_trim($content));
        $link = $conn->escape_string($link);

        // get dom instance here, so following methods
        // dont need to call it again
        $dom = str_get_html($content);

        // get <strong>, <b>, <em> tags from page
        $pageEmphasis = $this->getPageElems($dom, $content, "strong,em,b");

        // get headers <h1>-<h6>
        $pageHeaders = $this->getPageElems($dom, $content, "h1,h2,h3,h4,h5,h6");

        // strip out tags and remove useless html elements
        $content = $this->stripTags($content);

        $sql = <<<sql
        INSERT INTO pages (page_website, page_id, page_url, page_title, page_headers, page_emphasis, page_content)
        VALUES ('$name', '$link', '$link', '$pageTitle', '$pageHeaders', '$pageEmphasis', '$content');
sql;

        @$conn->query($sql);

        // update crawled pages count
        $this->crawledPagesCount += 1;

        if ($this->crawledPagesCount % 15 === 0) {
            // for every 15 pages crawled
            // update info in the `website` table
            $this->updateSiteStats($link, $this->crawledPagesCount);
        }

        return true;
    }


    /**
     * Update crawled links stats in the db
     *
     * @param [string|null] $link
     * @param [int] $linksCount
     * @return void
     */
    private function updateSiteStats($link, $linksCount)
    {
        $name = $this->sitename;
        $conn = $this->SQLConn;

        ////////////////////////////////////////
        // update info in the `website` table //
        ////////////////////////////////////////

        // $linksCount = (int) $conn->query("SELECT COUNT(*) FROM pages WHERE page_website='$name'")->fetch_row()[0];
        $date = date("jS F Y - l h:i:s A");

        if ($link) {
            $updateWebsiteInfo = <<<sql
            UPDATE websites
            SET pages_count='$linksCount', last_index_date='$date', last_indexed_url='$link'
            WHERE site_name='$name';
sql;
        } else {
            $updateWebsiteInfo = <<<sql
                UPDATE websites
                SET pages_count='$linksCount', last_index_date='$date'
                WHERE site_name='$name';
    sql;
        }

        if (!$conn->query($updateWebsiteInfo)) {
            $msg = "Failed to update pages count";
            $this->log($this->logFile, "- $msg");

            echo PHP_EOL . $msg;
        }
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

        if ((int) $site_exists->fetch_row()[0] === 0) {
            // site doesn't exist, insert it into the db

            $data = "INSERT INTO websites (site_url, site_name, pages_count, last_index_date, last_indexed_url, crawl_time)
            VALUES ('$url', '$name', 0, '" . date("jS F Y - l h:i:s A") . "', '$url', 'incomplete');";

            if (!$conn->query($data)) {
                $msg = "Failed to crawl website, could not insert data into the database - " . $conn->error;
                $this->log($this->logFile, "- $msg");

                exit(PHP_EOL . $msg);
            }
        } else {
            // else get last indexed url of this site from the database
            // so we continue where we stopped
            $last_indexed_url = $conn->query("SELECT last_indexed_url FROM websites WHERE site_name='$name'")
                ->fetch_row()[0];

            $this->lastIndexedURL = ($u = $last_indexed_url) ? $u : $url;
        }
    }

    /**
     * Get urls of all crawled pages from current website
     * (this is only call once curing crawler initialization)
     * @return array
     */
    private function getAllCrawledURLs()
    {
        $conn = $this->SQLConn;
        $name = $this->sitename;

        $res = [];
        $page_urls = $conn->query("SELECT page_url FROM pages WHERE page_website='$name'");
        while ($row = $page_urls->fetch_row()) {
            $res[$row[0]] = 1;
        }
        return $res;
    }

    /**
     * Remove html element from dom
     * @param  [DOMObject]        $dom        html node object from 'simple_html_dom' lib
     * @param  [Array[string]]    $selectors  selectors to be removed from dom
     */
    private function removeElem($dom, $selectors)
    {
        foreach ($selectors as $selector) {
            $elems = $dom->find($selector);
            foreach ($elems as $E) {
                $E->innertext = "";
            }
        }
    }

    /**
     * Get htmltag content from html string
     * @param   [DOMObject]
     * @param   [string]    $content    html string
     * @param   [string]    $tags       html tags to get
     *
     * @return [string]   string containing tag content
     */
    private function getPageElems($dom, $content, $tags)
    {
        $headers = $dom->find($tags);
        $str = "";
        foreach ($headers as $h) {
            $str .= preg_replace("#&[a-z0-9]+;#i", "", $h->plaintext) . " ";
        }
        return strip_tags($str);
    }

    /**
     * strip out tags from html document
     *
     * @param [string]  $string  HTML string
     *
     * @return [string]          HTML with tags stripped
     */
    private function stripTags($string)
    {
        // remove tags that shouldnt appear in the search result
        //  <script>, <style>
        //  <header>, <nav>, <ul>
        //  <aside>
        //  <button>
        //  <footer>
        //  <div role='navigation'>
        //  <div id='navbar'>
        //  <h1>-<h6>

        $dom = str_get_html($string);
        $this->removeElem($dom, [
            "script",
            "style",
            "header",
            "nav",
            "ul",
            "div[role=navigation]",
            "div#navbar",
            "aside",
            "button",
            "footer",
            "div.footer",
            "h1,h2,h3,h4,h5,h6" // we've already taken the contents in `addPageToDatabase()`
        ]);

        // get html as plaintext
        $string = $dom->plaintext;
        $string = preg_replace("#&[a-z0-9]+;#i", "", $string);

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
     * @param [string|false] $url url to fetch
     */
    private function getPageContent($url)
    {
        if (array_key_exists($url, $this->pageContentCache)) {
            return $this->pageContentCache[$url];
        }
        $cnt = @file_get_contents($url);
        if ($cnt) {
            return $this->pageContentCache[$url] = $cnt;
        }
        return false;
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
