<?php
ob_start();

set_time_limit(60);

const included = true;

require_once "php/inc/helpers.inc.php";
require_once "php/inc/setup_database.inc.php";
require_once "php/search/search.inc.php";
require_once "php/search/results_display.inc.php";

$get = $_GET; // shorthand access
if (!isset($get['q']) || $get['q'] === "") {
    header("Location: index.php");
}

// search query
$query = $conn->escape_string($get['q']);

// index to start at (pagination)
$startAt = isset($get['start']) ? $get['start'] : 1;
$startAt = ($startAt - 1) * 10;

// search db
$results = search($conn, "$query", $startAt); // returns [result, query time, totalRows] or null

if (is_array($results)) {
    $searchResult = $results[0];
    $queryTime = $results[1];
    $totalRows = $results[2];

    // feeling lucky?
    if (isset($get['lucky'])) {
        if ($searchResult->num_rows > 0) {
            $firstURL = $searchResult->fetch_row()[0];

            header("Location: $firstURL"); // redirect to first result
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?php echo $query." - " ?> localGoogoo search</title>

    <link rel="icon" href="assets/images/favicon.ico">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/styles.min.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
  <body style="max-width: 100%;">
    <nav style="position: relative; margin-bottom: 10px;" class="navbar navbar-default navbar-fixed-top">
      <div style="padding-left: 0;" class="container">

        <div class="row">
          <div class="col-md-10 col-12">
            <div class="navbar-header">
              <a class="navbar-brand" href="index.php">
                <img width="110" height="27" src='assets/images/localGoogoo.png'/>
              </a>
            </div>

            <div id="navbar" class="collapse navbar-collapse">
              <form action="" class="form-inline pc">
                <div class="form-group">
                  <input value="<?php echo $query; ?>" name="q" type="search" style="width: 400px;" class="form-control box input-lg" id="search_box">
                </div>
              </form>
            </div><!--/.nav-collapse -->
          </div>

          <div class="col-md-2 add-site">
            <a class="btn btn-outline-default index-site-button" href="sites.php" role="button">
            <span class="glyphicon glyphicon-plus" aria-hidden="true"></span>
            Add Websites
            </a>
          </div>
        </div>
        <form action="" class="form-inline mobile-search">
          <div class="form-group">
            <input value="<?php echo $query; ?>" name="q" type="search" class="form-control box input-lg" id="search_box">
          </div>
        </form>
      </div>

      <!-- update alert -->
      <div style="width: 27%; position: absolute; display: none; right: 0; top: 10px;"
          class="pc-only alert alert-info alert-dismissible version" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        New version available!
        <ul class='update-list'> </ul>
        <a href="http://github.com/kodejuice/localGoogoo"> [Go to Repo] </a>
      </div>
    </nav>

    <div class="container results-page">

        <?php
        if (!$results) {
            noResult();
        } // no result

        else {
            displayResults($searchResult);
        }
        ?>

        <?php
        function displayResults($data)
        {
            global
             $queryTime,
             $query,
             $totalRows,
             $startAt;
          
            if ($startAt === 0) {
                echo "<small class='results-count'> $totalRows Result(s)  (".round($queryTime, 2)." seconds) </small> <br><br>";
            } else {
                echo "<small class='results-count'> Page ".(($startAt/10) + 1)." of $totalRows Result(s)  (".round($queryTime, 2)." seconds) </small> <br><br>";
            }

            while ($row = $data->fetch_row()) {
                $url = $row[0];
                $title = !empty($row[1]) ? $row[1] : "$url";
                $content = $row[2];
        ?>
            </b></b></b>
            <div class="result">
              <a class='result-click-link' href='<?php echo $url ?>'>
                <div class="result-header">
                  <small class='result-url'><?php echo $url ?></small>
                  <span class="result-title"> <?php echo $title ?> </span>
                </div>
              </a>
              <div class="result-body">
                <span class='result-content'> <?php echo getDisplayContent($content, $query) ?> </span>
              </div>
            </div>
        <?php

            }

            // display pagination
            displayPaging($totalRows);
        }

        function noResult()
        {
            ?>
        <!-- no result -->
        <div style="font-size: 17px; padding: 7px;" class="result">
          <h3 style="margin-bottom: 15px">Your search - <b> <?php echo htmlentities($GLOBALS['query']); ?> </b> - did not match any document </b> </h3>
          <p> Suggestions: </p>
          <p> Make sure that all words are spelled correctly. </p>
          <ul>
            <li>Try different keywords.</li>
            <li>Try more general keywords.</li>
            <li>Try fewer keywords.</li>
          </ul>
        </div>

        <?php
        }
        ?>

      <br>
    </div>

    <script>
      (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
      (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
      m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
      })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

      ga('create', 'UA-75709223-2', 'auto');
      ga('send', 'pageview');
    </script>

    <script src="assets/js/libs/jquery.min.js"></script>
    <script src="assets/js/libs/bootstrap.min.js"></script>
    <script src="./version/version-tracker.js"></script>

    <script>
      $(function(){
      	const MAX_MOBILE_VIEW_WIDTH = 767;
        var $form = $("form"),

        // select which input based on screen width
        input = screen.width <= MAX_MOBILE_VIEW_WIDTH ?
          $(".mobile-search input#search_box")
          : $(".pc input#search_box");

        // focus input `onkeypress`
        $(document).keypress(function(e) {
          if (!input.is(":focus")) {
            var v = String.fromCharCode(e.which);
            if (v.match(/[a-z0-9]/i)) {
              input.focus();
              // no need to set new input value
              // its automatically done by the browser
            }
          }
        });


        // prevent whitespace search and empty input
        $form.on("submit", function(e) {
          var $input = $(this).find("input"),
          query = $input.val();

          if (!query || query.match(/^\s+$/)) {
            e.preventDefault();
            $input.focus();
          }

          // trim query string
          $input.val(query.replace(/\s+/g, ' '));
        });
      });
    </script>
  </body>
</html>
