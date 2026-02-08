<?php

use Nemesis\Core\Plugin;
use Nemesis\Core\Container;
use Nemesis\Plugins\CloudStorage\Storage;

Plugin::register('CloudStorage', function ($plugin) {
    // Register Storage class alias ?
    // Or just let user use namespace.
    
    // We can bind it to container for DI
    $container = Container::getInstance();
    $container->singleton('storage', function() {
        return new Storage(); 
        // Actually Storage methods are static, but we can return an instance wrapper if needed.
        // For now, static usage: Storage::disk('s3')->put(...)
    });
    
    // Helper function
    if (!function_exists('storage')) {
        function storage($disk = null) {
            return Storage::disk($disk);
        }
    }
});
