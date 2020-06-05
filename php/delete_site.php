<?php
/**
 * This file is part of the localGoogoo project
 *
 * Copyright (c) 2017, Sochima Biereagu
 * Under MIT License
 */

const included = true;
require_once "inc/setup_database.inc.php";

$post = $_POST;

if (isset($post["website_name"])) {
    $name = $conn -> escape_string($post["website_name"]);

    $delete = "DELETE FROM pages WHERE page_website='$name';";
    $delete .= "DELETE FROM websites WHERE site_name='$name';";

    @$conn->multi_query($delete);
}

sleep(1);

header("Location: sites.php?list");
