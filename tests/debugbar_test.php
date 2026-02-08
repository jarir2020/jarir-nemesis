<?php
// tests/debugbar_test.php

// Simulate a web request
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Capture output
ob_start();

// Include the public/index.php
// We need to make sure we don't exit()
// public/index.php doesn't have a return, it just runs.
// We might need to modify public/index.php to be testable or require the bootstrap logic.

// For now, let's just make a curl request to the local server if running, 
// OR simpler: Just require index.php and override header() and exit() if possible.
// PHP namespaces make overriding built-ins possible if we redeclare them in the namespace.

// Better approach for CLI test:
// Config::load...
// Route::dispatch...
// Check output.

require __DIR__ . "/../vendor/autoload.php";

use Nemesis\Core\Config;
use Nemesis\Core\Database;
use Nemesis\Core\PluginManager;

Config::load(__DIR__ . '/..');

$container = \Nemesis\Core\Container::getInstance();
$container->singleton(\Nemesis\Http\Request::class);
$container->singleton(\Nemesis\Router\Router::class);

$config = require __DIR__ . '/../config/config.php';
Database::connect($config['database']);

// Enable query log manually for test if plugin doesn't load?
// Plugin SHOULD load.
$pluginManager = PluginManager::getInstance();
$pluginManager->discover();

// Mock Router
$router = require __DIR__ . "/../routes/route.php";

// Define a route that returns HTML
$router->add('GET', '/test-debugbar', function() {
    // Explicitly set content type to header if we can, but in CLI headers are tricky.
    // The DebugBarMiddleware checks headers_list().
    // We can't easily mock headers_list() in basic PHP script without extension.
    // However, the middleware default expectation might need adjustment for CLI testing.
    
    // START HACK for testing:
    // DebugBarMiddleware checks:
    // 1. Content-Type header (defaults to true if no header?)
    // 2. Not AJAX.
    
    // We define a response that simulates HTML output
    header('Content-Type: text/html'); 
    echo "<html><body><h1>Test Page</h1></body></html>";
});

$response = $router->dispatch('/test-debugbar', 'GET');
if (is_string($response)) {
    echo $response;
} elseif (is_object($response) && method_exists($response, 'send')) {
    $response->send();
} elseif (is_object($response) && method_exists($response, 'getContent')) {
    echo $response->getContent();
}

$output = ob_get_clean();

if (strpos($output, 'nemesis-debugbar') !== false) {
    echo "PASS: DebugBar HTML found in response.\n";
} else {
    echo "FAIL: DebugBar HTML NOT found.\n";
    echo "Output length: " . strlen($output) . "\n";
    // echo "Output: " . $output . "\n";
}
