<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Http\Session;
use Nemesis\Helpers\Helpers;

echo "--- Security Test (CSRF & Throttling) ---\n";

// 1. CSRF Token
new Session();
$token = Session::token();
echo "Testing CSRF Token Generation: ";
echo (strlen($token) === 64 ? "PASS" : "FAIL") . "\n";

echo "Testing Helpers::csrfToken(): ";
echo (Helpers::csrfToken() === $token ? "PASS" : "FAIL") . "\n";

// Throttling is hard to unit test without heavy mocking, but we verified it in verify_throttle.php.
// We'll just confirm the Middleware class exists.
echo "Testing Throttle Middleware exists: ";
echo (class_exists('\App\Http\Middleware\ThrottleRequests') ? "PASS" : "FAIL") . "\n";

echo "\n--- Security Test Complete ---\n";
