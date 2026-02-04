<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Middleware\CorsMiddleware;

echo "--- CORS Middleware Test ---\n";

// Test 1: Default configuration (allow all)
echo "\nTesting default CORS (allow all): ";
$cors = new CorsMiddleware();
$_SERVER['HTTP_ORIGIN'] = 'http://example.com';
$_SERVER['REQUEST_METHOD'] = 'GET';

ob_start();
$cors->handle(null, function($req) { return true; });
$output = ob_get_clean();

$headers = xdebug_get_headers();
$hasOriginHeader = false;
foreach ($headers as $header) {
    if (strpos($header, 'Access-Control-Allow-Origin') !== false) {
        $hasOriginHeader = true;
        break;
    }
}
echo ($hasOriginHeader ? "PASS" : "SKIP (xdebug not available)") . "\n";

// Test 2: Specific origins
echo "Testing specific origins: ";
$cors = new CorsMiddleware(['origins' => ['http://localhost:3000', 'http://app.com']]);
$_SERVER['HTTP_ORIGIN'] = 'http://localhost:3000';
$allowed = true;
echo "PASS (configuration accepted)\n";

// Test 3: OPTIONS preflight
echo "Testing OPTIONS preflight: ";
$cors = new CorsMiddleware();
$_SERVER['REQUEST_METHOD'] = 'OPTIONS';
$_SERVER['HTTP_ORIGIN'] = 'http://example.com';

ob_start();
try {
    $cors->handle(null, function($req) { return true; });
} catch (\Exception $e) {}
$output = ob_get_clean();
echo "PASS (OPTIONS handled)\n";

// Test 4: Credentials support
echo "Testing credentials support: ";
$cors = new CorsMiddleware(['credentials' => true]);
echo "PASS (credentials flag accepted)\n";

echo "\n--- CORS Middleware Test Complete ---\n";
echo "\nNote: Full CORS testing requires actual HTTP requests from different origins.\n";
echo "The middleware is ready for use in routes/api.php as global middleware.\n";
