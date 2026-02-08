<?php

use Nemesis\Core\Plugin;

Plugin::register('audit', function($plugin) {
    // Register View Namespace
    \Nemesis\Core\View::addNamespace('audit', __DIR__ . '/views');

    // Register routes
    $plugin->route('audit', function() {
        $router = \Nemesis\Core\Container::getInstance()->make(\Nemesis\Router\Router::class);
        $router->add('GET', '/audit', [\Nemesis\Plugins\Audit\Controllers\AuditController::class, 'index']);
        $router->add('GET', '/audit/{id}', [\Nemesis\Plugins\Audit\Controllers\AuditController::class, 'show']);
    });
    
    // Register middleware
    // $plugin->middleware(YourMiddleware::class);
    
    // Register hooks
    // Plugin::hook('app.boot', function() {
    //     // Your hook logic
    // });
});
