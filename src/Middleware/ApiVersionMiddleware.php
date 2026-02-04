<?php

namespace Nemesis\Middleware;

class ApiVersionMiddleware {
    protected $version;
    protected $defaultVersion = 'v1';

    public function __construct($version = null) {
        $this->version = $version ?? $this->defaultVersion;
    }

    public function handle($request, $next) {
        // Check for version in URL path (e.g., /api/v1/users)
        $path = $_SERVER['REQUEST_URI'] ?? '';
        
        if (preg_match('#/api/(v\d+)/#', $path, $matches)) {
            define('API_VERSION', $matches[1]);
        } else {
            // Check for version in header
            $headerVersion = $_SERVER['HTTP_API_VERSION'] ?? $_SERVER['HTTP_X_API_VERSION'] ?? null;
            define('API_VERSION', $headerVersion ?? $this->defaultVersion);
        }

        return $next($request);
    }

    public static function getVersion() {
        return defined('API_VERSION') ? API_VERSION : 'v1';
    }
}
