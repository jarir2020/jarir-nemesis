<?php

namespace App\Http\Middleware;

class CheckForMaintenanceMode {
    public function handle($request, $next) {
        $maintenanceFile = __DIR__ . '/../../../storage/framework/down';
        
        if (file_exists($maintenanceFile)) {
            header('HTTP/1.1 503 Service Unavailable');
            header('Retry-After: 3600');
            echo json_encode([
                'error' => true,
                'message' => 'Application is down for maintenance. Please try again later.'
            ]);
            exit;
        }

        return $next($request);
    }
}
