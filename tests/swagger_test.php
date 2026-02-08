<?php
// tests/swagger_test.php

require __DIR__ . "/../vendor/autoload.php";

use Nemesis\Core\Config;
use Nemesis\Core\PluginManager;
use Nemesis\Core\Container;

Config::load(__DIR__ . '/..');
$container = Container::getInstance();
$container->singleton(\Nemesis\Http\Request::class);
$container->singleton(\Nemesis\Router\Router::class);

// Initialize router
// Note: router might be initialized in routes/route.php which we might need to include
// But here we want to test plugin routes.

// PluginManager::discover() loads plugin routes via Plugin::getRoutes()
// routes/route.php executes them.

$pluginManager = PluginManager::getInstance();
$pluginManager->discover();

// We need to execute route registration callback manually OR include routes/route.php
// Including routes/route.php is better as it simulates full stack.
$router = require __DIR__ . "/../routes/route.php";

// Test /api/docs (JSON)
echo "Testing /api/docs...\n";
ob_start();
try {
    $response = $router->dispatch('/api/docs', 'GET');
    
    // Handle response output/return
    if (is_string($response)) echo $response;
    elseif (is_object($response) && method_exists($response, 'send')) $response->send();
    elseif (is_object($response) && method_exists($response, 'getContent')) echo $response->getContent();
} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}

$json = ob_get_clean();

$data = json_decode($json, true);
if (json_last_error() === JSON_ERROR_NONE && isset($data['openapi'])) {
    echo "PASS: /api/docs returned valid OpenAPI JSON.\n";
} else {
    echo "FAIL: /api/docs returned invalid JSON.\nContent: " . substr($json, 0, 100) . "...\n";
}

// Test /api/documentation (HTML)
echo "Testing /api/documentation...\n";
ob_start();
$response = $router->dispatch('/api/documentation', 'GET');
// Handle response output/return
if (is_string($response)) echo $response;
elseif (is_object($response) && method_exists($response, 'send')) $response->send();
elseif (is_object($response) && method_exists($response, 'getContent')) echo $response->getContent();

$html = ob_get_clean();

if (strpos($html, 'swagger-ui') !== false) {
    echo "PASS: /api/documentation return Swagger UI.\n";
} else {
    echo "FAIL: /api/documentation did NOT return Swagger UI.\n";
}
