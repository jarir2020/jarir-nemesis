<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Http\ApiResponse;

echo "--- API Response Formatting Test ---\n";

// Capture output for testing
ob_start();

// Test 1: Success response
echo "\nTesting ApiResponse::success(): ";
try {
    ApiResponse::success(['user' => ['id' => 1, 'name' => 'John']], 'User found');
} catch (\Exception $e) {
    // This will exit, so we catch in a different way
}
$output = ob_get_clean();
$decoded = json_decode($output, true);
if (isset($decoded['success']) && $decoded['success'] === true && isset($decoded['data'])) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
}

// Test 2: Error response
ob_start();
echo "Testing ApiResponse::error(): ";
try {
    ApiResponse::error('Not found', 404);
} catch (\Exception $e) {}
$output = ob_get_clean();
$decoded = json_decode($output, true);
if (isset($decoded['success']) && $decoded['success'] === false && isset($decoded['message'])) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
}

// Test 3: Validation error
ob_start();
echo "Testing ApiResponse::validationError(): ";
try {
    ApiResponse::validationError(['email' => 'Invalid email format']);
} catch (\Exception $e) {}
$output = ob_get_clean();
$decoded = json_decode($output, true);
if (isset($decoded['errors']) && is_array($decoded['errors'])) {
    echo "PASS\n";
} else {
    echo "FAIL\n";
}

// Test 4: HTTP status codes
echo "Testing status code helpers: ";
$tests = ['notFound', 'unauthorized', 'forbidden', 'serverError'];
$passed = 0;
foreach ($tests as $method) {
    ob_start();
    try {
        ApiResponse::$method();
    } catch (\Exception $e) {}
    $output = ob_get_clean();
    if (!empty($output)) $passed++;
}
echo ($passed === count($tests) ? "PASS" : "FAIL") . " ($passed/" . count($tests) . ")\n";

echo "\n--- API Response Test Complete ---\n";
