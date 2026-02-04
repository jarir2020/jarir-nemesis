<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Core\Config;
use Nemesis\Http\Request;
use Nemesis\Router\Router;
use Nemesis\Core\Container;

Config::load(__DIR__ . '/../');
$container = Container::getInstance() ?: Container::setInstance(new Container());
$container->singleton(Request::class);

echo "--- Routing & DI Test ---\n";

$router = new Router($container);

// 1. Test Closure Route
$router->add('GET', '/test-closure', function() {
    return "Closure Hit";
});

// 2. Test Controller DI (Mock)
class MockController {
    public function index(Request $request) {
        return "Controller Hit with " . get_class($request);
    }
}

$router->add('GET', '/test-controller', [MockController::class, 'index']);

echo "Testing Closure: ";
$_SERVER['REQUEST_URI'] = '/test-closure';
$_SERVER['REQUEST_METHOD'] = 'GET';
$result = $router->dispatch('/test-closure', 'GET');
echo ($result === "Closure Hit" ? "PASS" : "FAIL") . "\n";

echo "Testing Controller DI: ";
$_SERVER['REQUEST_URI'] = '/test-controller';
$result = $router->dispatch('/test-controller', 'GET');
echo ($result === "Controller Hit with Nemesis\Http\Request" ? "PASS" : "FAIL") . "\n";

echo "\n--- Routing & DI Test Complete ---\n";
