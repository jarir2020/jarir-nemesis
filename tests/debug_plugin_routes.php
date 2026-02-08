<?php
require_once __DIR__ . '/vendor/autoload.php';

echo "=== Plugin Route Debug ===\n\n";

// Load config and plugins
\Nemesis\Core\Config::load(__DIR__);
$pluginManager = \Nemesis\Core\PluginManager::getInstance();
$pluginManager->discover();

echo "Active plugins: " . count($pluginManager->getActive()) . "\n";

// Check registered plugin routes
$pluginRoutes = \Nemesis\Core\Plugin::getRoutes();
echo "Plugin routes registered: " . count($pluginRoutes) . "\n";

foreach ($pluginRoutes as $name => $config) {
    echo "  Plugin: {$name}\n";
    echo "  Prefix: {$config['prefix']}\n";
}

// Load router
$container = \Nemesis\Core\Container::getInstance();
$router = require __DIR__ . '/routes/route.php';

echo "\nTotal routes in router: " . count($router->getRoutes()) . "\n";

// Check for plugin routes
$found = 0;
foreach ($router->getRoutes() as $route) {
    if (strpos($route['uri'], '/plugin/') === 0) {
        echo "  Found: {$route['method']} {$route['uri']}\n";
        $found++;
    }
}

echo "\nPlugin routes in router: {$found}\n";
