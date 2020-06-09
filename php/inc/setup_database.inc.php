<?php
/**
 * This file is part of the localGoogoo project
 *
 * Copyright (c) 2017, Sochima Biereagu
 * Under MIT License
 */

/* this script creates the localGoogoo database and its' tables if the're not yet created */
/* also makes the mysql $conn variable abailable for use */

require_once "helpers.inc.php";

$config_file = __DIR__."/../../config.json";

// if (!defined('included')) {
    // exit(PHP_EOL."Sorry, you cannot access this script directly".PHP_EOL);
// }

// creates config file if missing or corrupted
prepareConfigFile($config_file);

// get config data
$json = json_decode(file_get_contents($config_file), true);
extract($json);

$conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASSWORD);

// called from command line?
$isCMD = false;
if (isset($_SERVER['argc']) && isset($_SERVER['argv'])) {
    $isCMD = true;
}

if ($conn -> connect_error) {
    if ($isCMD) {
        exit(PHP_EOL."Error establishing a MySQL database connection, run ".PHP_EOL." $ ./bin/localgoogoo config".PHP_EOL);
    }

    $str = <<<text
  <html>
    <head>
      <meta name="viewport" content="width=device-width, initial-scale=1">
    </head>
     <big>
      <br> <br> <br>
      <fieldset>
       <legend> LocalGoogoo &lt; MySQL Connection Error &gt; </legend>
         Error establishing a MySQL database connection, please setup localgoogoo by <code>`cd`</code>ing into localgoogoo's directory and run <br>
          <pre>$ ./bin/localgoogoo config</pre>
         and make sure MySQL is running on your computer.
      </fieldset>
      <br>
      <b> $conn->connect_error </b>
     </big>
  </html>
text;

    exit($str);
}

/////////////////////
// Create database //
/////////////////////

$database = $conn->escape_string($DB_NAME); // database name, extracted from $json

$sql = "CREATE DATABASE IF NOT EXISTS ".$database;

if (!@$conn->query($sql)) {
    if ($isCMD) {
        exit(
            <<<sql

 Error creating MySQL database,
  please make sure the DB_USER('$DB_USER' in config) is valid and given the right privileges

 $conn->error

 run
  $ ./bin/localgoogoo config
sql
        ); // exit
    }

    exit(
        <<<sql

 Error creating MySQL database,
  please make sure the DB_USER('$DB_USER' in config) is valid and given the right privileges
<br><br>

 <b> $conn->error </b>

<br><br>
<code>cd</code> into localgoogoo's directory and run
  <pre>
  <code> $ ./bin/localgoogoo config </code>
  </pre>
  to rectify this.
sql
    ); // exit
}

$conn->select_db($database);


///////////////////
// Create tables //
///////////////////

// create tables if not exists :)
$table1 = <<<sql

CREATE TABLE IF NOT EXISTS websites (
    site_id INT AUTO_INCREMENT NOT NULL PRIMARY KEY,
    site_url VARCHAR(255) NOT NULL,
    site_name VARCHAR(255),
    pages_count INT NOT NULL,
    last_index_date VARCHAR(50) NOT NULL,
    last_indexed_url VARCHAR(255) NOT NULL,
    crawl_time VARCHAR(20) NOT NULL);
sql;

$table2 = <<<sql
  CREATE TABLE IF NOT EXISTS pages (
    `page_title` VARCHAR(250),
    `page_url` VARCHAR(250) NOT NULL ,
    `page_website` VARCHAR(100) NOT NULL ,
    `page_content` TEXT NOT NULL ,
    `page_headers` TEXT NOT NULL ,
    `page_emphasis` TEXT NOT NULL ,
    `page_id` VARCHAR(255) NOT NULL ,

    UNIQUE `id` (`page_id`),
    FULLTEXT `headers` (`page_headers`),
    FULLTEXT `emphasis` (`page_emphasis`),
    FULLTEXT `title` (`page_title`),
    FULLTEXT `content` (`page_content`),
    FULLTEXT `url` (`page_url`)
  ) ENGINE = MyISAM;
sql;

if (!$conn->query($table1) || !$conn->query($table2)) {
    exit("Failed to create tables in the database<br>\n\n<br>".$conn->error);
}
