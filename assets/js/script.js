/*jslint browser: true, evil: true, expr: true */
/*global jQuery */

/*
 * This file is part of the localGoogoo project
 *
 * Copyright (c) 2017, Sochima Biereagu
 * Under MIT License
 */

$(document).ready(
    function () {

        // switch to second tab
        if (location.search === "?list" || location.hash === "#manage") {
            $('.nav.nav-tabs a:eq(1)').tab('show');

            // delay first tab display to prevent display-blink
            // the first tab is hidden by default
            setTimeout(
                function () {
                    $('div[role=tabpanel]').eq(0).css('visibility', 'visible');
                }, 500
            );
        } else {
            // the first tab is hidden by default
            $('div[role=tabpanel]').eq(0).css('visibility', 'visible');
        }


        var crawler = {
            sec: 0, // timer seconds

            running: false,
            runningInBg: false, // running in background?

            timer: null,
            pagesCountTimer: null,

            pages_count: 0,

            ajRequest: null // to hold ajax request object
        };

        // time util
        // convert seconds to hour:min:sec
        var lt10 = new Function("s", "return s<10 ?'0'+s :s;"); // prefix '0' if less than 10

        function time(sec) 
        {
            var min, hr;

            if (sec < 0) {
                return "00:00:00";
            } else if (sec < 60) {
                return "00:00:" + lt10(sec);
            } else if (sec <= 3600) {
                min = Math.floor(sec / 60);
                sec = sec % 60;

                return "00:" + lt10(min) + ":" + lt10(sec);
            } else {
                hr = Math.floor(sec / 3600),
                min = Math.floor(sec / 60) % 60;
                sec = sec % 60;

                return lt10(hr) + ":" + lt10(min) + ":" + lt10(sec);
            }
        }

        // start timer
        function startTimer() 
        {
            crawler.timer = setInterval(
                function () {
                    if (crawler.runningInBg) {
                        $("button#start-btn").html("Crawling ... " + time(crawler.sec)+ " ("+crawler.pages_count+" pages)");
                    }

                    $("p#timer").html(time(crawler.sec));

                    crawler.sec += 1;
                }, 1000
            );
        }

        // opens dialog and starts timer
        function openDialog($dialog, $loader) 
        {
            $dialog.open();
            $loader.gSpinner();

            crawler.runningInBg = false;

            // start timer if not running
            if (crawler.sec === 0) {
                startTimer();
            }
        }

        // function to close displayed dialogs and stop ongoing ajax request and timers
        function closeDialog(aj /* xhr */, dialog, loader) 
        {
            aj = aj || crawler.ajRequest;

            aj.abort && aj.abort(); // stop the ajax request

            dialog.close();
            loader.gSpinner("hide");

            // stop timers
            clearInterval(crawler.timer);
            clearTimeout(crawler.pagesCountTimer);

            crawler.running = false;
        }

        // gets the number of crawled pages in a particular website
        function getPagesCount(sitename, siteurl, fn) 
        {
            $.get(
                './php/get_pages_count.php', {
                    sitename: sitename,
                    siteurl: siteurl
                }
            ).done(
                function (x) {
                        fn(x || 0);
                }
            );
        }


        var $dialog = $('[data-remodal-id=modal]').remodal(
            {
                closeOnCancel: false,
                closeOnOutsideClick: false
            }
        ),

        $loader = $("[data-remodal-id=modal] #spinner-container");


        // Ctrl+X - close crawler dialog
        $("html").bind(
            'keydown', "Ctrl+x", function () {
            		// check if already running in background
            		if (!crawler.runningInBg) {
				            if (crawler.running) {
				            		alert("Crawling/Indexing will continue at background");
				                crawler.runningInBg = true;
				            }
				            $dialog.close();
				            $loader.gSpinner("hide");
                }
            }
        );


        // add new website form 
        $(".form-container form").on(
            "submit", function (ev) {
                ev.preventDefault();

                var webname = this[0].value,
                weburl = this[1].value;

                // make an ajax request to the start_crawler.php script
                var aj = $.ajax(
                    {
                        beforeSend: function () {
                            // dont continue this request if theres an ongoing ajax request already
                            if (crawler.running) {
                                openDialog($dialog, $loader);
                                crawler.runningInBg = false;
                                return false;
                            }

                            // display dialog and loading spinner
                            // also starts timer
                            openDialog($dialog, $loader);

                            crawler.ajRequest = aj;
                            crawler.running = true;

                            $('em#site_url').html(weburl);

                            var pagesCount = function pagesCount(webname, weburl)
                            {
                                getPagesCount(
                                    webname, weburl, function (pages_count) {
                                        crawler.pages_count = pages_count;

                                        $("#crawled_pages_count").html(pages_count + " Cralwed Pages");

                                        crawler.pagesCountTimer = setTimeout(
                                            function () {
                                                pagesCount(webname, weburl);
                                            }, 1000
                                        );
                                    }
                                );
                            }

                            // start pages count timer
                            pagesCount(webname, weburl);
                        },

                        url: "./php/start_crawler.php",
                        method: "post",
                        data: {
                            web_name: webname,
                            web_url: weburl
                        },
                        timeout: (86400 * 20) * 1000
                    }
                );


                // ajax events
                aj.done(
                    function (xhr) {
                        setTimeout(
                            function () {
                                crawler.running = false;
                                // display success
                                closeDialog(aj, $dialog, $loader);
                                alert("Process Complete!");

                                window.location = "?list";
                            }, 500
                        );
                    }
                );

                aj.error(
                    function (x, t, m) {
                        m = m || "Couldnt fetch resource, please try again";
            
                        if (m === "canceled") {
                            return;
                        }

                        // close dialog, abort xhr request then alert error message
                        setTimeout(
                            function () {
                                if (crawler.running) {
                                    crawler.running = false;
                                    closeDialog(aj, $dialog, $loader);
                                    alert(m);
                                }
                            }, 1400
                        );
                    }
                );

            }
        );


        // form - prevent empty input and excess whitespace
        var $form = $("nav.navbar form"),
        $input = $form.find("input");

        $input.eq(1).focus();

        $form.submit(
            function (e) {
                var query = $input.val();

                if (query === "" || query.match(/^\s+$/)) {
                    e.preventDefault();
                    $input.focus();
                    return;
                }

                // trim query string
                $input.val(query.replace(/\s+/g, ' '));
            }
        );


        // Ctrl+return - 'Feelink Lucky?' Shortcut
        $("input, html").bind(
            'keydown', "Ctrl+return", function () {
                var query = $input.val();

                $input.val(query = query.replace(/\s+/g, ' '));

                if (query && !query.match(/^\s+$/)) {
                    window.location = "./search.php?q=" + query + "&lucky=1";
                }
            }
        );

        // tabs
        $('.nav.nav-tabs a').click(
            function (e) {
                e.preventDefault();
                $(this).tab('show');
            }
        );


        // re-index site
        $(".re-index").click(
            function (ev) {
                if (!confirm("Re-index this website?")) {
                    ev.preventDefault();
                    return;
                }
            }
        );


        // delete site
        $(".delete-site").click(
            function (ev) {
                if (!confirm("Delete this indexed website?")) {
                    ev.preventDefault();
                }
            }
        );

    }
);
