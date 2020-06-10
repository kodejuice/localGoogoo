<?php


// result page-content to display in search results page
// highlighting words found in the search query
function getDisplayContent($content, $query)
{
    $parts = explode(".", $content);
    $words = explode(" ", $query);

    $r = '';

    foreach ($parts as $sntnc) {
        foreach ($words as $w) {
            if (preg_match("/\b".escape_regex($w)."\b/i", $sntnc, $m)) {
                $m = $m[0];

                $sntnc = str_replace("$m", "<b>$m</b>", $sntnc); // highlight keyword
                $r = $sntnc . "$r."; // prefix important part
            } elseif (count($parts)) {
                $r .= $sntnc; // append less important
            }
        }
    }

    return substr($r, 0, 140);
}



?>
