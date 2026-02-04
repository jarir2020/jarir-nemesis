<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Core\Log;

echo "--- Logging Test ---\n";

$date = date('Y-m-d');
$logFile = __DIR__ . "/../storage/logs/nemesis-{$date}.log";

if (file_exists($logFile)) unlink($logFile);

Log::info("Test logging system");

echo "Testing Log File Creation: ";
echo (file_exists($logFile) ? "PASS" : "FAIL") . "\n";

echo "Testing Log Content: ";
$content = file_get_contents($logFile);
echo (strpos($content, "Test logging system") !== false ? "PASS" : "FAIL") . "\n";

echo "\n--- Logging Test Complete ---\n";
