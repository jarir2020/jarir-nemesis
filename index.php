<?php

/**
 * Nemesis - A PHP Framework For LightWeight API Design
 *
 * @package  JarirAhmed
 * @author   Jarir Ahmed <jarircse16@gmail.com>
 */

require __DIR__ . '/vendor/autoload.php';

use Nemesis\Core\Config;
use Nemesis\Core\Database;
use Nemesis\Core\View;

Config::load(__DIR__);
View::addPath(base_path('views'));

// Load plugins early in boot process
$pluginManager = \Nemesis\Core\PluginManager::getInstance();
$pluginManager->discover();

// Fail-fast health check
\Nemesis\Core\Bootstrap::check();

$container = new \Nemesis\Core\Container();
\Nemesis\Core\Container::setInstance($container);
$container->singleton(\Nemesis\Http\Request::class);
$container->singleton(\Nemesis\Router\Router::class);
$container->singleton(\Nemesis\Services\Mailer::class);

// Auto-discover service providers shipped by installed packages (extra.nemesis).
// Providers only register lazy container bindings, so unused packages stay
// dormant in vendor/ until the app actually resolves them.
$packageProviders = [];
foreach ((new \Nemesis\Core\PackageManifest(__DIR__))->providers() as $providerClass) {
    if (! class_exists($providerClass)) {
        continue;
    }
    try {
        $provider = new $providerClass($container);
        $provider->register();
        $packageProviders[] = $provider;
    } catch (\Throwable $e) {
        error_log("Nemesis package provider register failed [{$providerClass}]: " . $e->getMessage());
    }
}
foreach ($packageProviders as $provider) {
    try {
        $provider->boot();
    } catch (\Throwable $e) {
        error_log('Nemesis package provider boot failed [' . get_class($provider) . ']: ' . $e->getMessage());
    }
}

// App Debug Check
$appDebug = filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN);
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
    // v7.1.1 (Gap 7): also load optional web.php / api.php so apps deployed
    // with the root directory as the document root behave the same as apps
    // served from public/. Wrapped in is_file() so missing optional files
    // don't fatal.
    if (is_file(__DIR__ . '/routes/web.php')) {
        require __DIR__ . '/routes/web.php';
    }
    if (is_file(__DIR__ . '/routes/api.php')) {
        require __DIR__ . '/routes/api.php';
    }

    $pipeline = (new \Nemesis\Http\Pipeline())
        ->send($request)
        ->through($kernel->getMiddleware());

    $response = $pipeline->then(function ($request) use ($router) {
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

        return $router->dispatch($uri, $request->method());
    });

    $response->send();
    $pipeline->terminate($request, $response);
}
