<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Core\Bootstrap;

echo "--- Environment Doctor/Bootstrap Test ---\n";

// We won't call Bootstrap::check() directly because it calls die() on failure.
// But we can check if it exists.
echo "Testing Bootstrap class exists: ";
echo (class_exists('\Nemesis\Core\Bootstrap') ? "PASS" : "FAIL") . "\n";

echo "Testing Startup check presence: ";
echo (method_exists('\Nemesis\Core\Bootstrap', 'check') ? "PASS" : "FAIL") . "\n";

echo "\n--- Environment Doctor Test Complete ---\n";
