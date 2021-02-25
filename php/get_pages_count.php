<?php
/**
 * This file is part of the localGoogoo project
 *
 * Copyright (c) 2017, Sochima Biereagu
 * Under MIT License
 */

/*
 * Get the pages count (total crawled pages) of a website in the database
 */

const included = true;

require_once __DIR__ . "/inc/setup_database.inc.php";

if (isset($_GET['sitename']) && isset($_GET['siteurl'])) {
    $name = trim($conn->escape_string(urldecode($_GET['sitename'])));
    $url = trim($conn->escape_string(urldecode($_GET['siteurl'])));

    if (!preg_match("#(\.(\w+)|/)$#", $url)) {
        $url .= "/";
    }

    $data = $conn->query(
        <<<sql
        SELECT pages_count
        FROM websites
        WHERE site_name='$name' AND site_url='$url'
sql
    );

    $row = $data->fetch_row();

    echo $row[0] ?? 0;
} else {
    exit("Sorry, you cant access this script");
}
