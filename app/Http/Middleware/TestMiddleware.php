<?php

namespace App\Http\Middleware;

class TestMiddleware {
    public function handle($request, $next) {
        $logFile = __DIR__ . '/../../../storage/framework/middleware_test.log';
        file_put_contents($logFile, "Passed through TestMiddleware at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        
        $response = $next($request);
        
        file_put_contents($logFile, "Returning from TestMiddleware at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
        
        return $response;
    }
}
