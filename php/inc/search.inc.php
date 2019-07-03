<?php
/**
 * This file is part of the localGoogle project
 *
 * Copyright (c) 2017, Sochima Biereagu
 * Under MIT License
 */

if (!defined('included')) {
    exit("Sorry you cannnot access this file directly");
}

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

    $searchQuery = <<<sql
	    SELECT page_url, page_title, page_content
	    FROM pages
	    WHERE MATCH (page_url, page_title, page_content) AGAINST('$query' IN BOOLEAN MODE)
	    LIMIT $startAt, 10;
sql;

    $queryTime = microtime(true); // start time
    $results = $conn->query($searchQuery);
    $queryTime = microtime(true) - $queryTime; // total time took


    // fetch total rows
    // same query without LIMIT
    $resultsCount = <<<count
	    SELECT page_url, page_title, page_content
	    FROM pages
	    WHERE MATCH (page_url, page_title, page_content) AGAINST('$query' IN BOOLEAN MODE)
count;
    
    $resultsCount = $conn->query($resultsCount);

    $results = boolval($results) ? $results : false;
    if ($results) {
        if ($results->num_rows > 0) {
            return [$results, $queryTime, $resultsCount->num_rows];
        }

        $results = false;
    }


    if (!$results) {
        // invalid query or no result
        // try different search technique

        $sqlQuery = "SELECT page_url, page_title, page_content FROM pages WHERE page_content";
        $words = explode(" ", $query);

        $query_no_limit = '';

        for ($i = 0; $i < $count = count($words); $i += 1) {
            if ($i === $count - 1) { // last loop
                $sqlQuery .= " LIKE '%$words[$i]%'";

                $query_no_limit = $searchQuery;

                $sqlQuery .= " LIMIT $startAt, 10";
            } else {
                $sqlQuery .= " LIKE '%$words[$i]%' OR page_content";
            }
        }

        $queryTime = microtime(true); // start time
        $results = $conn->query($sqlQuery);
        $queryTime = microtime(true) - $queryTime; // total time took

        // fetch total rows
        $allResults = $conn->query($query_no_limit);
    }


    if (!!$results && $results->num_rows > 0) {
        return [$results, $queryTime, $allResults->num_rows];
    }
    
    return null; // no result
}
