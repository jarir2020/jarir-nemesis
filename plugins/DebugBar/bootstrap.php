<?php

use Nemesis\Core\Plugin;
use Nemesis\Core\Database;
use Nemesis\Plugins\DebugBar\DebugBarMiddleware;

Plugin::register('DebugBar', function ($plugin) {
    // Enable Query Logging
    Database::enableQueryLog();

    // Register Middleware
    try {
        $container = \Nemesis\Core\Container::getInstance();
        // Check if Router is bound (Container doesn't have has method, so we check using reflection or try/catch)
        // Since we are in try/catch block already:
        $router = $container->make(\Nemesis\Router\Router::class);
        $router->globalMiddleware(new DebugBarMiddleware());
    } catch (\Exception $e) {
        // Silently fail if router not available
    }
    
});
