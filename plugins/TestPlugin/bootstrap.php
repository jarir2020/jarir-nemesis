<?php

use Nemesis\Core\Plugin;

Plugin::register('testplugin', function($plugin) {
    // Register routes
    $plugin->route('plugin', function() {
        global $router;
        
        // Test route
        $router->add('GET', '/plugin/test', function() {
            header('Content-Type: application/json');
            echo json_encode([
                'message' => 'TestPlugin is working!',
                'plugin' => 'testplugin',
                'version' => '1.0.0',
                'framework' => 'Nemesis'
            ]);
        });
        
        // Info route
        $router->add('GET', '/plugin/info', function() {
            header('Content-Type: application/json');
            echo json_encode([
                'name' => 'TestPlugin',
                'description' => 'Demonstration plugin for Nemesis framework',
                'features' => [
                    'Routes',
                    'Middleware',
                    'Event Hooks',
                    'Sandboxing'
                ]
            ]);
        });
    });
    
    // Register hooks
    Plugin::hook('app.boot', function() {
        error_log('[TestPlugin] Application booted successfully');
    });
    
    Plugin::hook('plugin.loaded', function($name) {
        if ($name === 'testplugin') {
            error_log('[TestPlugin] Plugin loaded and active');
        }
    });
});
