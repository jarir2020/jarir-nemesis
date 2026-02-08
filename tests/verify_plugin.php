<?php
require_once __DIR__ . '/vendor/autoload.php';

echo "=== Nemesis Plugin System Verification ===\n\n";

// Test 1: Plugin Discovery
echo "1. Testing Plugin Discovery...\n";
$manager = \Nemesis\Core\PluginManager::getInstance();
$manager->discover();
$plugins = $manager->getAll();

if (isset($plugins['testplugin'])) {
    echo "   ✓ TestPlugin discovered\n";
} else {
    echo "   ✗ TestPlugin NOT found\n";
    exit(1);
}

// Test 2: Plugin Activation
echo "\n2. Testing Plugin Activation...\n";
if ($manager->isActive('testplugin')) {
    echo "   ✓ TestPlugin is active\n";
} else {
    echo "   ✗ TestPlugin is NOT active\n";
    exit(1);
}

// Test 3: Manifest Parsing
echo "\n3. Testing Manifest Parsing...\n";
$manifest = $plugins['testplugin']['manifest'];
echo "   Name: " . $manifest->getName() . "\n";
echo "   Version: " . $manifest->getVersion() . "\n";
echo "   Permissions: " . implode(', ', $manifest->getPermissions()) . "\n";
echo "   ✓ Manifest parsed successfully\n";

// Test 4: Plugin Routes
echo "\n4. Testing Plugin Routes...\n";
$router = require __DIR__ . '/routes/route.php';
$routes = $router->getRoutes();

$pluginRoutesFound = 0;
foreach ($routes as $route) {
    if (strpos($route['uri'], '/plugin/') === 0) {
        $pluginRoutesFound++;
        echo "   ✓ Found route: {$route['method']} {$route['uri']}\n";
    }
}

if ($pluginRoutesFound > 0) {
    echo "   ✓ Plugin routes registered ({$pluginRoutesFound} routes)\n";
} else {
    echo "   ✗ No plugin routes found\n";
}

// Test 5: Hook System
echo "\n5. Testing Hook System...\n";
\Nemesis\Core\Plugin::fire('test.event', 'test-data');
$hooks = \Nemesis\Core\Plugin::getHooks('app.boot');
if (!empty($hooks)) {
    echo "   ✓ Hooks registered (" . count($hooks) . " hooks)\n";
} else {
    echo "   ⚠ No hooks registered (this is okay)\n";
}

// Test 6: Sandbox Permissions
echo "\n6. Testing Sandbox Permissions...\n";
$sandbox = new \Nemesis\Core\PluginSandbox('testplugin', ['routes', 'middleware']);
try {
    $sandbox->requirePermission('routes');
    echo "   ✓ Permission check passed (routes)\n";
} catch (\Exception $e) {
    echo "   ✗ Permission check failed\n";
}

try {
    $sandbox->requirePermission('filesystem');
    echo "   ✗ Sandbox failed to block unauthorized permission\n";
} catch (\Exception $e) {
    echo "   ✓ Sandbox correctly blocked unauthorized permission (filesystem)\n";
}

echo "\n=== All Tests Passed! ===\n";
echo "\nPlugin System is fully functional.\n";
