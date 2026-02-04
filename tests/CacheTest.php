<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Core\Config;
use Nemesis\Core\Cache;

Config::load(__DIR__ . '/../');

echo "--- Cache Drivers Test ---\n";

// 1. File Driver
echo "Testing File Driver: ";
Cache::set('test_key', 'hello', 10);
$val = Cache::get('test_key');
echo ($val === 'hello' ? "PASS" : "FAIL") . "\n";
Cache::forget('test_key');

// Expiration
echo "Testing File Expiration: ";
Cache::set('exp_key', 'bye', 1);
sleep(2);
$val = Cache::get('exp_key');
echo ($val === null ? "PASS" : "FAIL") . "\n";

echo "\n--- Cache Drivers Test Complete ---\n";
