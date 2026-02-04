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
    
    // Debug: List what was actually extracted
    $extracted = is_dir($extractDir);
    $hasFiles = false;
    if ($extracted && is_dir($extractDir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $hasFiles = true;
                break;
            }
        }
    }
    
    echo ($extracted && $hasFiles ? "PASS" : "FAIL") . "\n";
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// Cleanup
@unlink($zipFile);
@unlink($storage . '/test_file.txt');
if (is_dir($extractDir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($extractDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? @rmdir($file->getRealPath()) : @unlink($file->getRealPath());
    }
    @rmdir($extractDir);
}

echo "\n--- Archiving & Compression Test Complete ---\n";
