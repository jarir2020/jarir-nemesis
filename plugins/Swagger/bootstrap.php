<?php

use Nemesis\Core\Plugin;
use Nemesis\Plugins\Swagger\Generator;

Plugin::register('Swagger', function ($plugin) {
    $plugin->route('/api', function() {
        // We need to register routes here.
        // But Nemesis Router is usually loaded in index.php.
        // Plugin::route callback is executed in routes/route.php
        
        $router = \Nemesis\Core\Container::getInstance()->make(\Nemesis\Router\Router::class);

        // JSON Endpoint
        $router->add('GET', '/api/docs', function() {
            header('Content-Type: application/json');
            echo Generator::generate();
        });

        // UI Endpoint
        $router->add('GET', '/api/documentation', function() {
            // Render view
            // We can use a simple require for the view
            require __DIR__ . '/views/swagger.php';
        });
    });
});
