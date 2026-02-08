<?php
namespace Nemesis\Plugins\TestPlugin;

/**
 * TestMiddleware - Example plugin middleware
 */
class TestMiddleware {
    public function handle($request) {
        // Add custom header
        header('X-Plugin: TestPlugin');
        header('X-Plugin-Version: 1.0.0');
        
        return $request;
    }
}
