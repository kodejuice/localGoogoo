<?php

define('WINDOW_LEN', 174);



// get the portion of the web page to display in the
// search results page
function getDisplayContent($document, $query)
{
    // use a sliding window to slide over entire document calculating each
    // window string's relevance score, when done return the substring
    // with highest relevance score

    $GLOBALS['most_occuring'] = most_occuring($document);

    $best = "";
    $bestScore = -1;

    $substrings = total_substrings($document);

    foreach ($substrings as $str) {
        $score = relevance_score($str, $query, $document);

        if ($score > $bestScore) {
            $best = $str;
            $bestScore = $score;
        }
    }

    // echo "($bestScore) (".readability_score($best).")";
  
    // bold-ify query keywords in the string
    $best = highlight_matches($best, $query);

    return strtolower($best[0]) == $best[0] // first character is in low caps
     ? "...$best..."
     : "$best...";
}




////////////
// helper //
////////////


// higlight matches of $query in $string
// using <b>
//
function highlight_matches($string, $query)
{
    $query = preg_replace("#[^a-z0-9]+#i", " ", $query); // split query by any non-alphanumberic character
    $qry_kwords = explode(" ", $query);

    foreach ($qry_kwords as $kwrd) {
        if (strlen($kwrd)>2 and preg_match("/\b".escape_regex($kwrd)."\b/i", $string, $m)) {
            $m = $m[0];
            $string = preg_replace("#\b$m\b#", "<b>$m</b>", $string); // highlight keyword
        }
    }

    return $string;
}


// return the next substring start position after $i
// $i += WINDOW_LEN/2
//
function next_pos($str, $i)
{
    $l = strlen($str);
    $k = $i + (WINDOW_LEN >> 1);

    while ($k < $l && !ctype_alnum($str[$k])) {
        $k += 1;
    }

    return $k>=$l ? -1 : $k;
}


// split $content into substrings
// each substring begins with a new word
//
function total_substrings($content)
{
    $i = 0;

    do {
        yield substr($content, $i, WINDOW_LEN);

        // advance
        $i = next_pos($content, $i);
    } while ($i != -1);
}



//////////////////////////////////////////////
// how relevant is a substring to a query ? //
//////////////////////////////////////////////


// count occurence of $q in $string
//
function freq($q, $string)
{
    if (strlen($q) < 1) {
        return 0;
    }
    return substr_count(strtolower($string), strtolower($q));
}


// frequency of the most occuring term in document
//
function most_occuring($doc)
{
    $max = 1;
    $hash = [];
    $terms = explode(" ", strtolower($doc));

    foreach ($terms as $t) {
        if (!ctype_alnum($t)) {
            continue;
        }
        if (!array_key_exists($t, $hash)) {
            $hash[$t] = 0;
        }
        $hash[$t] += 1;
        $max = max($max, $hash[$t]);
    }

    return $max;
}


// Implementation of the TFIDF algorithm (https://en.wikipedia.org/wiki/Tf–idf)
// although with a slight difference, this one works for multiple query keywords

function TF($t, $string)
{
    return .5 * (1 + (freq($t, $string) / $GLOBALS['most_occuring']));
}


function IDF($t, $D)
{
    $N = strlen($D) - (WINDOW_LEN - 1);
    $count = 1 + (freq($t, $D)/$N);
    return $N / $count;
}


// TODO: improve this
function relevance_score($string, $query, $document, $t=-1)
{
    $keywords = explode(" ", $query);
    $sc = 1;

    foreach ($keywords as $qi) {
        if (strlen($qi) < 1) {
            continue;
        }
        $sc += TF($qi, $string);
    }

    $score = ($sc * IDF($query, $document));

    // add relevance score of right part of string
    //  cant tell why this works better than the left substring :(
    if ($t == -1) {
        $right_substr = substr($string, strlen($string)/2);
        // $left_substr = substr($string, 0, strlen($string)/2);
        $score += relevance_score($right_substr, $query, $document, 1)/2;
    }

    // add the readability score
    // this should filter out strings with mostly non-alphabetic characters
    $score += readability_score($string) * 6.5;
    /* 6.5 is carefully chosen (although no training done),
      its used to make the readability-score value affect $score, which is in the thousands */;

    return $score;
}


// how readable is a string ?
//
function readability_score($string)
{
    // basic alogrithm
    // we expect 90% of the string to be alphabets
    $chars = str_split($string);
    $alphabets = 0;

    foreach ($chars as $c) {
        if (ctype_alpha($c)) {
            $alphabets += 1;
        }
    }

    // percentage of alphabets in the string
    $perc = ($alphabets / strlen($string));

    return ($perc / .9) * 100;
}
