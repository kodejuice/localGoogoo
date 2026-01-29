<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../php/crawler/crawler.class.php';

// Setup Test Environment
$testDir = __DIR__ . '/test_server_env';
if (!is_dir($testDir)) {
    mkdir($testDir);
}

// Download minimal PDF
$pdfUrl = "https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf";
$pdfContent = @file_get_contents($pdfUrl);
if (!$pdfContent) {
    // Fallback if download fails: use a known valid base64 encoded PDF (small one)
    $pdfContent = base64_decode("JVBERi0xLjQKJcOkw7zDtsOfCjIgMCBvYmoKPDwvTGVuZ3RoIDMgMCBSL0ZpbHRlci9GbGF0ZURlY29kZT4+CnN0cmVhbQp4nE2NPQvCMBCG9/yK400G0uTj1qVbB8FBEAdb6V+Q5sE/35qA4A3v8dzD814iS+4yxs4G66K04yG+4xR9M2A4jGq8qS26s+Gz+QWWRT96h5x7lM+2bfmcn5/JtChxYw5eH3d1K9c3QnQ9l5tqS6/oK75+yN8L2x9jL1t/ACoCJ0wKZW5kc3RyZWFtCmVuZG9iagozIDAgb2JqCjgwCmVuZG9iago1IDAgb2JqCjw8L1BhcmVudCA0IDAgUi9NZWRpYUJveFswIDAgNTk1LjI4IDg0MS44OV0vVHlwZS9QYWdlL1Jlc291cmNlczw8L1Byb2NTZXRbL1BERi9UZXh0XS9Gb250PDwvRjEgMSAwIFI+Pj4+L0NvbnRlbnRzIDIgMCBSPj4KZW5kb2JqCjEgMCBvYmoKPDwvVHlwZS9Gb250L1N1YnR5cGUvVHlwZTEvQmFzZUZvbnQvSGVsdmV0aWNhPj4KZW5kb2JqCjQgMCBvYmoKPDwvVHlwZS9QYWdlcy9Db3VudCAxL0tpZHNbNSAwIFJdPj4KZW5kb2JqCjYgMCBvYmoKPDwvVHlwZS9DYXRhbG9nL1BhZ2VzIDQgMCBSPj4KZW5kb2JqCjcgMCBvYmoKPDwvUHJvZHVjZXIoU2ltcGxlIFBERiAxLjAuMCkvQ3JlYXRpb25EYXRlKEQ6MjAyMTAxMjAxNzU0NTYrMDAnMDAnKS9Nb2REYXRlKEQ6MjAyMTAxMjAxNzU0NTYrMDAnMDAnKT4+CmVuZG9iagp4cmVmCjAgOAowMDAwMDAwMDAwIDY1NTM1IGYgCjAwMDAwMDAyNjMgMDAwMDAgbiAKMDAwMDAwMDAxNSAwMDAwMCBuIAowMDAwMDAwMjQ1IDAwMDAwIG4gCjAwMDAwMDAzNTEgMDAwMDAgbiAKMDAwMDAwMDEwNSAwMDAwMCBuIAowMDAwMDAwNDEwIDAwMDAwIG4gCjAwMDAwMDA0NTkgMDAwMDAgbiAKdHJhaWxlcgo8PC9TaXplIDgvUm9vdCA2IDAgUi9JbmZvIDcgMCBSPj4Kc3RhcnR4cmVmCjU3NQolJUVPRgo=");
    // This PDF contains "Hello World"
}
file_put_contents($testDir . '/sample.pdf', $pdfContent);
file_put_contents($testDir . '/index.html', '<html><body><a href="sample.pdf">PDF</a></body></html>');

// Start PHP Server
$port = 8999;
$host = "127.0.0.1";
$cmd = sprintf('php -S %s:%d -t %s > /dev/null 2>&1 & echo $!', $host, $port, $testDir);
$pid = exec($cmd);

// Wait for server to start
sleep(1);

// Mock DB
class MockMySQLi {
    public $connect_error = null;
    public $error = null;
    public $inserts = [];

    public function escape_string($str) {
        return addslashes($str);
    }

    public function query($sql) {
        $trimmed = trim($sql);
        if (stripos($trimmed, "INSERT INTO pages") === 0) {
            $this->inserts[] = $sql;
            return true;
        }
        if (stripos($trimmed, "SELECT COUNT(*) FROM websites") === 0) return new MockResult([[0]]);
        if (stripos($trimmed, "SELECT page_url FROM pages") === 0) return new MockResult([]);
        return true;
    }
}

class MockResult {
    private $data;
    public function __construct($data) { $this->data = $data; }
    public function fetch_row() { return array_shift($this->data); }
}

try {
    echo "Running Test...\n";
    $mockDb = new MockMySQLi();
    $crawler = new LGCrawler("TestSite", "http://$host:$port/index.html", $mockDb);

    $crawler->onComplete(function($t){});

    $crawler->startCrawler(function(){});

    // Verify
    $pdfFound = false;
    foreach ($mockDb->inserts as $sql) {
        if (strpos($sql, "sample.pdf") !== false && (strpos($sql, "Dummy PDF file") !== false || strpos($sql, "Hello World") !== false)) {
            $pdfFound = true;
            echo "SUCCESS: Found PDF insert with expected content.\n";
            break;
        }
    }

    if (!$pdfFound) {
        echo "FAILURE: PDF insert not found.\n";
        echo "Inserts: " . print_r($mockDb->inserts, true) . "\n";
        if (file_exists(__DIR__ . "/../log.txt")) {
            echo "Log content:\n" . file_get_contents(__DIR__ . "/../log.txt") . "\n";
        }
        exit(1);
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    // Cleanup
    echo "Cleaning up...\n";
    if ($pid) exec("kill $pid");
    array_map('unlink', glob("$testDir/*"));
    if (is_dir($testDir)) rmdir($testDir);
    // The crawler logs to relative path ../../log.txt from crawler class
    // which is root log.txt.
    if (file_exists(__DIR__ . "/../log.txt")) unlink(__DIR__ . "/../log.txt");
}
