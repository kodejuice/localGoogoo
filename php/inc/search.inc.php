<?php
/**
 * This file is part of the localGoogoo project
 *
 * Copyright (c) 2017, Sochima Biereagu
 * Under MIT License
 */

if (!defined('included')) {
    exit("Sorry you cannnot access this file directly");
}

// this script uses the MySQL full-text search technique
// presented in the paper: https://www.researchgate.net/publication/268785605_Full-text_search_engine_using_MySQL

/**
 * This function searches the database for our query
 *
 * @param [resource] $conn    mysql connection
 * @param [string]   $query   search query
 * @param [int]      $startAt pagination start index
 *
 * @return [array]             search results
 */

function search($conn, $query, $startAt)
{
    $query = $conn->escape_string($query);
    $startAt = $conn->escape_string($startAt);

    $U = 1.14;  // url relevance
    $T = 1.14;  // title relevance
    $C = 1;     // content relevance
    $H = 1.3;   // headers relevance
    $S = 1.2;   // emphasis relevance

    $searchQuery = <<<sql
	    SELECT page_url, page_title, page_content,
	    MATCH(page_url) AGAINST ('$query' IN BOOLEAN MODE) AS relUrl,
	    MATCH(page_title) AGAINST ('$query' IN BOOLEAN MODE) AS relTitle,
	    MATCH(page_headers) AGAINST ('$query' IN BOOLEAN MODE) AS relHeaders,
	    MATCH(page_emphasis) AGAINST ('$query') AS relStrong,
	    MATCH(page_content) AGAINST ('$query' IN BOOLEAN MODE) AS relContent
	    FROM pages
	    WHERE MATCH(`page_title`, `page_url`, `page_content`, `page_headers`, `page_emphasis`) AGAINST('$query' IN BOOLEAN MODE)
	    ORDER BY relUrl*$U + relTitle*$T + relContent*$C + relHeaders*$H + relStrong*$S DESC
	    LIMIT $startAt, 10;
sql;

    // total query time
    $queryTime = microtime(true); // start time
    $results = $conn->query($searchQuery);
    $queryTime = microtime(true) - $queryTime; // total time took

    // fetch total rows
    // same query without LIMIT
    $resultsCount = <<<count
	    SELECT page_id FROM pages
	    WHERE MATCH (page_url, page_title, page_content, page_headers, page_emphasis) AGAINST('$query' IN BOOLEAN MODE)
count;

    // return results
    $resultsCount = $conn->query($resultsCount);
    $results = boolval($results) ? $results : false;
    if ($results) {
        if ($results->num_rows > 0) {
            return [$results, $queryTime, $resultsCount->num_rows];
        }
        $results = false;
    }

    if (!$results) {
        // no result
        // try a different search technique
        return normal_search($conn, $query, $startAt);
    }
}


/**
 * plain mysql LIKE '%search%'
 */
function normal_search($conn, $query, $startAt) {
    $sqlQuery = "SELECT page_url, page_title, page_content FROM pages WHERE";

    $words = explode(" ", $query);
    for ($i = 0; $i < $count = count($words); $i += 1) {
        if ($i === $count - 1) $sqlQuery .= " page_title LIKE '%$words[$i]%';";
        else $sqlQuery .= " page_title LIKE '%$words[$i]%' OR ";
    }

    // total query time
    $queryTime = microtime(true); // start time
    $results = $conn->query($sqlQuery);
    $queryTime = microtime(true) - $queryTime; // total time took

    // fetch total rows
    // same query without LIMIT
    $qry = "SELECT page_id FROM pages WHERE";
    for ($i = 0; $i < $count = count($words); $i += 1) {
        if ($i === $count - 1) $qry .= " page_title LIKE '%$words[$i]%';";
        else $qry .= " page_title LIKE '%$words[$i]%' OR ";
    }
    $allResults = $conn->query($qry);

    if ($results && $results->num_rows > 0) {
        return [$results, $queryTime, $allResults->num_rows];
    }

    // still no result ?
    // just give up
    return null;
}

