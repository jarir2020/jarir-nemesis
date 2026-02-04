<?php
require_once __DIR__ . '/vendor/autoload.php';

use Nemesis\Core\Config;
use Nemesis\Http\Request;
use Nemesis\Http\Pipeline;
use Nemesis\Http\Session;
use App\Http\Kernel;

Config::load(__DIR__);
$container = \Nemesis\Core\Container::getInstance();
if (!$container) {
    $container = new \Nemesis\Core\Container();
    \Nemesis\Core\Container::setInstance($container);
}
$container->singleton(Request::class);

echo "--- CSRF Protection Test ---\n";

// 1. Initial request to generate token
new Session();
$token = Session::token();
echo "Generated Token: $token\n";

$kernel = new Kernel();
$request = $container->make(Request::class);

// 2. Simulate POST without token
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [];
echo "\nTesting POST WITHOUT token (Expected: 419 Page Expired)...\n";

try {
    (new Pipeline())
        ->send($request)
        ->through($kernel->getMiddleware())
        ->then(function($request) {
            echo "SUCCESS: Reached destination (Should NOT happen!)\n";
        });
} catch (\Exception $e) {
    echo "Caught Exception: " . $e->getMessage() . "\n";
}

// 3. Simulate POST WITH token
$_POST['_token'] = $token;
echo "\nTesting POST WITH token (Expected: SUCCESS)...\n";
(new Pipeline())
    ->send($request)
    ->through($kernel->getMiddleware())
    ->then(function($request) {
        echo "SUCCESS: CSRF Token matched, reached destination.\n";
    });

echo "\n--- CSRF Protection Test Complete ---\n";
