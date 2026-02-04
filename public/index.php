<?php
require "../vendor/autoload.php";

use Nemesis\Core\Config;
use Nemesis\Core\Database;

Config::load(__DIR__ . '/..');

$container = new \Nemesis\Core\Container();
$container->singleton(\Nemesis\Http\Request::class);
$container->singleton(\Nemesis\Router\Router::class);

set_exception_handler(['Nemesis\Core\ErrorHandler', 'handleException']);
set_error_handler(['Nemesis\Core\ErrorHandler', 'handleError']);

$config = require '../config/config.php';

Database::connect($config['database']);

// Load routes from external file
$router = require "../routes/route.php";

// Normalize URI and dispatch
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
