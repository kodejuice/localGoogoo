/*
 * This file is part of the localGoogle project
 *
 * Copyright (c) 2017, Sochima Biereagu
 * Under MIT License
 */

/*
 * This worker script gets infromation of a website from the database while its beign crawled
 */

self.importScripts('xhr.js');

var sitename, siteurl,
    post = self.postMessage;

self.addEventListener(
    'message', function (msg) {

        if (msg.data.start) {
            if (!msg.data.isRunning) { return self.stop(); // ajax request aborted, terminate worker
            }

            sitename = encodeURIComponent(msg.data.sitename);
            siteurl = encodeURIComponent(msg.data.siteurl);

            start(); // start getting info about the website as the crawler runs in the browser
        }

    }
);


function start(url) 
{
    // make ajax request to the db and get info of the currently crawled website
    // since the crawler script (crawler.class.php), adds the pages to the database as it crawls
    url = url || './get_pages_count.php?sitename='+sitename+'&siteurl='+siteurl;

    load(
        url, function (d) {
            post(d.responseText);

            setTimeout(
                function () {
                    start(url);
                }, 700
            );
        }
    );
}
