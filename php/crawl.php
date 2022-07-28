<?php
/**
 * This file is part of the localGoogoo project
 *
 * Copyright (c) 2017, Sochima Biereagu
 * Under MIT License
 */

// this script initiates the crawler

set_time_limit(86400 * 31);

const included = true;

require_once "inc/helpers.inc.php";
require_once "inc/setup_database.inc.php";
require_once "crawler/crawler.class.php";


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
        exit("\n$usageText".PHP_EOL." Invalid URL or Website name".PHP_EOL);
    }
} else {
    exit("This script is meant to be run from the command line");
}


$name = $conn -> escape_string($name);
$url = $conn -> escape_string(strtolower($url));

// clear/create log file
file_put_contents(__DIR__."/../log.txt", "");

$crawler = new LGCrawler($name, $url, $conn);

// on-crawl-complete handler
$crawler->onComplete(
    function ($timeTook) use ($crawler, $conn, $name) {

        // update crawl-time in database
        $updateCrawlTime = "UPDATE websites SET crawl_time='$timeTook' WHERE site_name='$name';";

        if (!$conn->query($updateCrawlTime)) {
            $msg = "Crawling complete, but failed to update crawl time";
            $crawler->log($crawler->logFile, "- $msg");

            echo PHP_EOL.$msg;
        }

        if ($crawler->tooLarge) {
            $crawler->log($crawler->logFile, "This website cannot be properly crawled, pages are too large!");
        }

        $crawler->log($crawler->logFile, "Crawl complete!");

        echo PHP_EOL.PHP_EOL."Process complete! ".secToTime($timeTook).PHP_EOL.PHP_EOL;
    }
);


echo PHP_EOL."Crawling website ...".PHP_EOL.PHP_EOL;

// start crawler!
$crawler->startCrawler(
    function () use ($name, $url, $conn, $crawler) {
        // callback called as pages crawl,

        echo progress($crawler->crawledPagesCount." Crawled Pages");
    }
);
