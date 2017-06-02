<?php
/**
 * This file is part of the localGoogle project
 *
 * Copyright (c) 2017, Sochima Biereagu
 * Under MIT License
 */

// imitiate running the crawler on the command ine
// from web

require_once "inc/helpers.inc.php";

function run($cmd)
{
    exec("php -q $cmd");
}

$post = $_POST;

if (isset($post['web_name']) && isset($post['web_url'])) {
    $name = ($post['web_name']);
    $url = ($post['web_url']);

    if (isInvalid($name, $url)) {
        // Invalid URL or name
        exec("echo Website URL Inaccessible! Could not crawl >> ../log.txt");
    } else {
        run("crawl.php \"\" \"$name\" \"$url\"");
    }

    return;
}

echo "Sorry, you cannot access this script directly";
