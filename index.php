<?php

/**
 * Nemesis - A PHP Framework For LightWeight API Design
 *
 * @package  JarirAhmed
 * @author   Jarir Ahmed <jarircse16@gmail.com>
 */

// Enable CORS (ONLY for web requests)
if (PHP_SAPI !== 'cli') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");

    // Handle preflight
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

require "vendor/autoload.php";

use Nemesis\Core\Config;
use Nemesis\Core\Database;

Config::load(__DIR__);

// Fail-fast health check
\Nemesis\Core\Bootstrap::check();

$container = new \Nemesis\Core\Container();
\Nemesis\Core\Container::setInstance($container);
$container->singleton(\Nemesis\Http\Request::class);
$container->singleton(\Nemesis\Router\Router::class);
$container->singleton(\Nemesis\Services\Mailer::class);

// App Debug Check
$appDebug = getenv('APP_DEBUG') === 'true';
if ($appDebug) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

set_exception_handler(['Nemesis\Core\ErrorHandler', 'handleException']);
set_error_handler(['Nemesis\Core\ErrorHandler', 'handleError']);

$config = require __DIR__ . '/config/config.php';
Database::connect($config['database']);

// Middleware Pipeline
if (PHP_SAPI !== 'cli') {
    $kernel = new \App\Http\Kernel();
    $request = $container->make(\Nemesis\Http\Request::class);
    $router = require __DIR__ . '/routes/route.php';

    (new \Nemesis\Http\Pipeline())
        ->send($request)
        ->through($kernel->getMiddleware())
        ->then(function ($request) use ($router) {
            // Normalize URI by removing base folder (if any)
            $uri = $_SERVER['REQUEST_URI'];
            $scriptName = $_SERVER['SCRIPT_NAME'];

            $basePath = str_replace('\\', '/', dirname($scriptName));
            if ($basePath !== '/' && substr($basePath, -1) === '/') {
                $basePath = rtrim($basePath, '/');
            }

            if ($basePath !== '/' && strpos($uri, $basePath) === 0) {
                $uri = substr($uri, strlen($basePath));
            }

            if ($uri === '' || $uri[0] !== '/') {
                $uri = '/' . $uri;
            }

            return $router->dispatch($uri, $_SERVER['REQUEST_METHOD']);
        });
}
