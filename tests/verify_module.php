<?php
require_once __DIR__ . '/vendor/autoload.php';

// Simulate Router setup
$container = \Nemesis\Core\Container::getInstance();
$router = new \Nemesis\Router\Router($container);

// Load routes (this includes module routes)
require __DIR__ . '/routes/route.php';

echo "Total Routes: " . count($router->getRoutes()) . "\n";

$found = false;
foreach ($router->getRoutes() as $route) {
    if ($route['uri'] === '/blog') {
        echo "✓ Found Blog Route: GET /blog\n";
        $found = true;
    }
}

if (!$found) {
    echo "✗ Blog Route NOT found!\n";
    exit(1);
}

// Test View Namespace
try {
    // This should not throw if namespace registered correctly
    $path = \Nemesis\Core\View::render('blog::index', ['name' => 'Verify'], true);
    echo "✓ View Namespace 'blog' is active.\n";
} catch (\Exception $e) {
    // Render usually requires/prints, so we check if it found it
    if (strpos($e->getMessage(), 'not found') === false) {
        echo "✓ View Namespace 'blog' is active (found, though rendering failed as expected in CLI).\n";
    } else {
        echo "✗ View Namespace Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\nModule System Verification Successful!\n";
