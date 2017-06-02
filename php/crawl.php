<?php
/**
 * This file is part of the localGoogle project
 *
 * Copyright (c) 2017, Sochima Biereagu
 * Under MIT License
 */

// this script initiates the crawler

set_time_limit(86400 * 31);

const included = true;

require_once "inc/helpers.inc.php";
require_once "inc/setup_database.inc.php";
require_once "inc/crawler.class.php";


// ran from CLI ?
if (isset($_SERVER['argc']) && isset($_SERVER['argv'])) {
    $usageText = "USAGE: $argv[1] crawl [website name] [website url]".PHP_EOL;

    if ($argc <> 4) {
        exit("\n$usageText");
    }

    $name = $argv[2] ?? ''; // sitename
    $url = $argv[3] ?? ''; // siteurl

    if (!preg_match("#(\.(\w+)|/)$#", $url)) {
        $url .= "/";
    }

    if (isInvalid($name, $url)) {
        exit("\n$usageText".PHP_EOL." Invalid URL or Website name");
    }

    // clear log file
    if (file_exists($log = "../log.txt")) {
        file_put_contents($log, "");
    }
} else {
    exit("This script is meant to be run from the command line");
}


$name = $conn -> escape_string($name);
$url = $conn -> escape_string(strtolower($url));


$crawler = new LGCrawler($name, $url, $conn);

// on-crawl-complete handler
$crawler->onComplete(
    function ($timeTook) use ($crawler, $conn, $name) {

        // update crawl-time in database
        $updateCrawlTime = "UPDATE websites SET crawl_time='$timeTook' WHERE site_name='$name';";

        if (!$conn->query($updateCrawlTime)) {
            $msg = "Crawling complete, but failed to update crawl time";
            $crawler->log($cralwer->logFile, "- $msg");

            echo PHP_EOL.$msg;
        }

        if ($crawler->tooLarge) {
            $crawler->log($crawler->logFile, "This website cannot be properly crawled, pages are too large!");
        }

        $crawler->log($crawler->logFile, "Crawl complete!");

        echo PHP_EOL.PHP_EOL."Process complete! ".secToTime($timeTook);
    }
);


echo PHP_EOL."Crawling website ...".PHP_EOL.PHP_EOL;


// start crawler!
$crawler->startCrawler(
    function () use ($name, $url, $conn) {
        // callback called as pages crawl,
    
        // get crawled pages count from database as we crawl
        $getCount = $conn->query(
            <<<sql
        SELECT pages_count
        FROM websites
        WHERE site_name='$name' AND site_url='$url'
sql
        );

        $row = $getCount->fetch_row();

        echo progress(($row[0] ?? 0)." Crawled Pages");
    }
);
