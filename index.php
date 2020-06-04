<!DOCTYPE bobhtml>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="description" content="A minimalistic search engine for searching locally saved websites.">
    <title>localGoogle</title>

    <link rel="icon" href="./assets/images/favicon.ico">
    <link href="./assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="./assets/css/styles.min.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
      <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
  </head>
  <body>

    <div class="container">

      <div class="container-center">
        <div class="m">
          <div class="home-header">
            <div id="title"> <!--BG IMAGE ELM--> </div>
            <div id="title-mobile">
              <img style="border:none;margin:8px 0" height="56" src="assets/images/localGoogle.png" width="220" alt="localGoogoo"/>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 col-md-offset-3">
              <form action="./php/search.php">
                <div class="form-group">
                  <input name="q" type="search" class="form-control input-lg home-search" id="search_box">
                </div>

                <div id="search_buttons">
                  <button type="submit" class="btn btn-primary btn-lg home-btn">Search</button>
                  <button type="submit" class="btn btn-primary btn-lg home-btn" name="lucky" title="Use Ctrl+Enter" value="true">I'm Feeling Lucky</button>
                </div>
              </form>

              <a href="./php/sites.php" class="btn btn-outline-primary"> Manage Indexed Websites </a>

            </div><!-- /.col-md-6 col-md-offset-3 -->
          </div> <!-- /.row -->

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

    <script src="./assets/js/libs/jquery.min.js"></script>
    <script src="./assets/js/libs/bootstrap.min.js"></script>
    <script src="./assets/js/libs/jquery.hotkeys.js"></script>

    <script>
      $(function(){

        var input = $("input"),
            $form = $("form");
        input.focus();

        // prevent whitespace search and empty input
        $form.on("submit", function(e) {
          var $input = input,
          query = $input.val();

          if (!query || query.match(/^\s+$/)) {
            e.preventDefault();
            $input.focus();
          }

          // trim query string
          $input.val(query.replace(/\s+/g, ' '));
        });


        /* KEYPRESS */

        // Ctrl+return - 'Feelink Lucky?' Shortcut
        $("input, html").bind('keydown', "Ctrl+return", function (){
           var query = input.val();

           input.val(query = query.replace(/\s+/g, ' '));
           
           if (query && !query.match(/^\s+$/)){
              window.location = "./php/search.php?q="+query+"&lucky=1";
           }
        });

        // focus input `onkeypress`
        $(document).keypress(function(e) {
          if (!input.is(":focus")) {
            var v = String.fromCharCode(e.which);
            if (v.match(/[a-z0-9]/i)) {
              input.focus();
            }
          }
        });


      });
    </script>

  </body>
</html>
