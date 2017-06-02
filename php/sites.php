<?php
/* get crawled websites information */

const included = true;

require "inc/helpers.inc.php";
require "inc/setup_database.inc.php";

$websites = $conn->query(
    <<<sql
  SELECT site_name, pages_count, site_url, last_index_date, crawl_time FROM websites
  ORDER BY last_index_date DESC
sql
);

$allPagesCount = $conn->query("SELECT COUNT(*) FROM pages");

?>


<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>localGoogle - Crawl new site</title>

    <link rel="icon" href="../assets/images/favicon.ico">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/styles.min.css" rel="stylesheet">
    <link href="../assets/libs/remodal/remodal.css" rel="stylesheet">
    <link href="../assets/libs/remodal/remodal-default-theme.css" rel="stylesheet">
    <link href="../assets/libs/g-spinner/css/gspinner.min.css" rel="stylesheet">    
    
    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
  <body>
  
    <nav style="position: relative;" class="navbar navbar-default navbar-fixed-top">
      <div class="container">
        <div class="navbar-header">
          <a class="navbar-brand" href="./../index.php">
            <img width="113" height="27" src='../assets/images/localGoogle.png'/>
          </a>
        </div>

        <div id="navbar" class="collapse navbar-collapse">
          <form action="search.php" class="form-inline">
            <div class="form-group">
              <input name="q" type="search" style="width: 400px;" class="form-control box input-lg" id="search_box">
            </div>
          </form>
        </div><!--/.nav-collapse -->
      </div>
    </nav>

    <!-- dialog and loading spinner -->
    <div data-remodal-id="modal">
      <big align='right'> <b> <p id='timer'> 00:00:00 </p> </b> </big>

      <h1> localGoogle WebCrawler </h1>
      <div id="spinner-container"> </div>
      <br>
      <big>
        <em id='site_url'> </em>
        <p> <b id='crawled_pages_count'> ... </b> </p>
      </big>
      <big> <cite title="close dialog"> Close Dialog (Ctrl + X) </cite> </big>
    </div>

    <div class="container remodal-bg">

      <ul class="nav nav-tabs">
        <li role="presentation" class="active"><a href="#add">Index new website</a></li>
        <li role="presentation"><a href="#manage">Manage Indexed Websites</a></li>
      </ul>

        <?php
        // display log.txt content
        // and clear the file
        
        if (file_exists("../log.txt")) {
            $logs = file_get_contents("../log.txt");

            if ($logs !== "") {
                ?>

           <div style="width: 40%;margin-top: 9px;" class="alert alert-info alert-dismissible" role="alert">
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>

                <?php echo nl2br($logs); ?>
           </div>

        <?php
            file_put_contents("../log.txt", "");
            }
        }
        ?>

      <!-- Tab panes -->
      <div class="tab-content">
        <!-- add new site -->

        <div role="tabpanel" style="visibility: hidden;" class="tab-pane active" id="add">

          <!-- add new site -->
          <div style="width: 40%;margin-top: 9px;" class="alert alert-info alert-dismissible" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>

            You can also index/crawl new websites via the command line, <code>./bin/localgoogle crawl <b>[website_name] [website_url]</b> </code>
          </div>

          <div class="form-container">
            <form action="./start_crawler.php" method="POST" class="form-indexSite">
              <h2>Index New Website</h2>
              <br>

              <label for="websiteName" class="sr-only">Website Name</label>
              <input type="text" value="<?php echo $conn->escape_string($_POST['name']??'');?>" name="web_name" class="form-control box" placeholder="Website Name" required autofocus>

              <label for="websiteUrl" class="sr-only">Website URL</label>
              <input type="url" value="<?php echo $conn->escape_string($_POST['url']??'');?>" name="web_url" class="form-control box" placeholder="Website Url" required>

              <button id="start-btn" class="btn btn-lg btn-primary btn-block box" type="submit">Start Indexing</button>
            </form>

          </div> <!-- /.form-container -->  
        </div>


        <!-- manage indexed sites -->
        <div role="tabpanel" class="tab-pane fade" id="manage">

          <div class="row" style="margin-top: 30px;">
            <div class="col-md-10 col-md-offset-1">  

              <table class="table table-bordered table-striped">
                <tr>
                  <td>
                    <b>Website</b>
                  </td>
                  <td>
                    <b>Last Indexed Date</b>
                  </td>
                  <td title="time took to index the website">
                    <b>Time Took</b>
                  </td>
                  <td>
                    <b>Action</b>
                  </td>
                </tr>

                <?php
                $c = 0;

                while ($row = $websites->fetch_row()) {
                    $row = array_map(
                        function ($v) {
                            return htmlentities($v);
                        }, $row
                    );

                    $c += 1; ?>
                <tr>
                  <td><?php echo "$row[0] (".(($row[1]<2)?"$row[1] page":"$row[1] pages").") <br> <a href='$row[2]'>$row[2]</a>"; ?></td>

                  <td><?php echo $row[3]; ?></td>

                  <td>
                    <?php echo secToTime($row[4]); ?>
                  </td>

                  <td>

                  <form style="display: inline;" class="form-inline" action="sites.php" method="POST">
                  
                    <input type="hidden" name="url" value="<?php echo $row[2]; ?>">
                    <input type
                    ="hidden" name="name" value="<?php echo $row[0]; ?>">
                    <button type="submit" class="btn btn-default re-index" title="Re-Index" role="button">
                      <span class="glyphicon glyphicon-repeat" aria-hidden="true"></span>
                    </button>

                  </form>

                  <form style="display: inline;" class="form-inline" action="delete_site.php" method="POST">

                    <input type="hidden" name="website_name" value="<?php echo $row[0]; ?>">
                    <button type="submit" class="btn btn-default delete-site" title="Delete" role="button">
                      <span class="glyphicon glyphicon-trash" aria-hidden="true"></span>
                    </button>

                  </form>

                  </td>
                </tr>
                <?php

                }
                
                if ($c === 0) {
                    echo "<h3>No Website Indexed yet!</h3><br>";
                } else {
                    $count = $allPagesCount->fetch_row()[0];

                    echo "<h4> ".(($count<2)?"$count Page Indexed":"$count Pages Indexed"). "</h4>";
                }

                // $database - from the 'setup_database.inc.php' script
                echo "<h4 align='right'> <em> Database<b>:</b> $database</em> </h4>";
                ?>

              </table>
            </div>

          </div>
        </div>

      </div>

    </div>
    <script>
      (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
      (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
      m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
      })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

      ga('create', 'UA-75709223-2', 'auto');
      ga('send', 'pageview');
   </script>

    <script src="../assets/js/libs/jquery.min.js"></script>
    <script src="../assets/js/libs/bootstrap.min.js"></script>
    <script src="../assets/js/libs/jquery.hotkeys.js"></script>

    <script src="../assets/libs/remodal/remodal.min.js"></script>
    <script src="../assets/libs/g-spinner/js/g-spinner.min.js"></script>

    <script src="../assets/js/script.js"></script>
  </body>
</html>
