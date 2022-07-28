<?php

/**
 * This file is part of the localGoogoo project
 *
 * Copyright (c) 2021, Sochima Biereagu
 * Under MIT License
 */


/**
 * This script queries the DB for a query
 *  (calls the search function )
 *
 * Returns the response as JSON string
 */

ob_start();

set_time_limit(60);

const included = true;

require_once __DIR__ . "/../inc/setup_database.inc.php";
require_once "search.inc.php";
require_once "results_display.inc.php";

// search query
$query = $conn->escape_string($_GET['q']);

// search db
$db_response = search($conn, $query); // returns [result, query time, totalRows] or null



/**
 * format DB reponse
 *
 * @param   [array|null]  $db_response
 *
 * @return  [array]
 */
function search_result($db_response) {
    $query = $GLOBALS['query'];

    if (!is_array($db_response)) {
        return [];
    }

    $search_result = $db_response[0];
    $query_time = $db_response[1];
    $total_rows = $db_response[2];

    $r = [
        'queryTime' => $query_time,
        'total' => $total_rows,
        'results' => [],
    ];

    while ($row = $search_result->fetch_row()) {
        $r['results'][] = [
            'url' => $row[0],
            'title' => !empty($row[1]) ? $row[1] : $row[0],
            'content' => strip_tags(getDisplayContent($row[2], $query)),
        ];
    }

    return $r;
}

echo json_encode(search_result($db_response));
