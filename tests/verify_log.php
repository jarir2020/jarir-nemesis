<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Core\Config;
use Nemesis\Core\Log;

Config::load(__DIR__ . '/../');

echo "--- Logging System Test ---\n";

Log::info('This is an info message', ['user_id' => 1]);
Log::warning('This is a warning message');
Log::error('This is an error message', ['exception' => 'RuntimeError']);

$date = date('Y-m-d');
$logFile = __DIR__ . "/storage/logs/nemesis-{$date}.log";

if (file_exists($logFile)) {
    echo "SUCCESS: Log file created at $logFile\n";
    echo "Content Preview:\n";
    echo file_get_contents($logFile);
} else {
    echo "FAILURE: Log file NOT created.\n";
}

echo "\n--- Logging System Test Complete ---\n";
