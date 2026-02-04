<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Core\Storage\Archive;
use Nemesis\Core\Config;

Config::load(__DIR__ . '/../');

echo "--- Archiving & Compression Test ---\n";

$storage = __DIR__ . '/../storage';
$zipFile = $storage . '/test_archive.zip';
$extractDir = $storage . '/extracted_test';

// 1. Create a dummy file
file_put_contents($storage . '/test_file.txt', "Hello Nemesis Archive!");

echo "Testing ZIP Creation: ";
try {
    Archive::zip($zipFile)
           ->addFile($storage . '/test_file.txt')
           ->addDirectory(__DIR__ . '/../config')
           ->save();
    
    echo (file_exists($zipFile) ? "PASS" : "FAIL") . "\n";
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

echo "Testing Extraction: ";
try {
    Archive::extract($zipFile, $extractDir);
    echo (is_dir($extractDir) && file_exists($extractDir . '/test_file.txt') ? "PASS" : "FAIL") . "\n";
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// Cleanup
@unlink($zipFile);
@unlink($storage . '/test_file.txt');

echo "\n--- Archiving & Compression Test Complete ---\n";
