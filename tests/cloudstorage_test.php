<?php
// tests/cloudstorage_test.php

require __DIR__ . "/../vendor/autoload.php";

use Nemesis\Core\Config;
use Nemesis\Core\PluginManager;
use Nemesis\Plugins\CloudStorage\Storage;

Config::load(__DIR__ . '/..');

$container = \Nemesis\Core\Container::getInstance();

// Load plugins
$pluginManager = PluginManager::getInstance();
$pluginManager->discover();

// Test local storage write
try {
    Storage::disk('local')->write('test.txt', 'Hello CloudStorage!');
    
    if (Storage::disk('local')->fileExists('test.txt')) {
        echo "PASS: File written successfully.\n";
        
        $content = Storage::disk('local')->read('test.txt');
        if ($content === 'Hello CloudStorage!') {
            echo "PASS: File content verified.\n";
        } else {
            echo "FAIL: Content mismatch.\n";
        }
        
        // Clean up
        Storage::disk('local')->delete('test.txt');
    } else {
        echo "FAIL: File not found after write.\n";
    }
} catch (\Exception $e) {
    echo "FAIL: Exception: " . $e->getMessage() . "\n";
}
