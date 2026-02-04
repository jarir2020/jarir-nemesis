<?php

// Simulate web environment for index.php
$_SERVER['REQUEST_URI'] = '/api/users';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SCRIPT_NAME'] = '/index.php';

// Define a constant to tell index.php we want to run even in CLI for testing
define('TestingMiddleware', true);

// We need to modify index.php slightly to allow this test
// Or just replicate the logic here.

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Core\Config;
use Nemesis\Core\Database;
use Nemesis\Http\Request;
use Nemesis\Http\Pipeline;
use App\Http\Kernel;

Config::load(__DIR__ . '/../');
$container = \Nemesis\Core\Container::getInstance();
if (!$container) {
    $container = new \Nemesis\Core\Container();
    \Nemesis\Core\Container::setInstance($container);
}
$container->singleton(Request::class);

$config = require __DIR__ . '/../config/config.php';
Database::connect($config['database']);

echo "Starting middleware verification test...\n";

$kernel = new Kernel();
$request = $container->make(Request::class);
$router = require __DIR__ . '/../routes/route.php';

(new Pipeline())
    ->send($request)
    ->through($kernel->getMiddleware())
    ->then(function ($request) use ($router) {
        echo "Inside Pipeline destination (Router dispatch placeholder)\n";
        return true;
    });

echo "Middleware verification test complete.\n";
