<?php

namespace Nemesis\Middleware;

class CorsMiddleware {
    protected $allowedOrigins;
    protected $allowedMethods;
    protected $allowedHeaders;
    protected $allowCredentials;
    protected $maxAge;

    public function __construct($config = []) {
        $this->allowedOrigins = $config['origins'] ?? ['*'];
        $this->allowedMethods = $config['methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        $this->allowedHeaders = $config['headers'] ?? ['Content-Type', 'Authorization', 'X-Requested-With'];
        $this->allowCredentials = $config['credentials'] ?? false;
        $this->maxAge = $config['max_age'] ?? 86400; // 24 hours
    }

    public function handle($request, $next) {
        // Get origin from request
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Check if origin is allowed
        if ($this->isOriginAllowed($origin)) {
            header("Access-Control-Allow-Origin: " . ($this->allowedOrigins[0] === '*' ? '*' : $origin));
            
            if ($this->allowCredentials) {
                header("Access-Control-Allow-Credentials: true");
            }
        }

        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header("Access-Control-Allow-Methods: " . implode(', ', $this->allowedMethods));
            header("Access-Control-Allow-Headers: " . implode(', ', $this->allowedHeaders));
            header("Access-Control-Max-Age: " . $this->maxAge);
            http_response_code(204);
            exit;
        }

        // Add exposed headers for regular requests
        header("Access-Control-Expose-Headers: Content-Length, X-JSON");

        return $next($request);
    }

    protected function isOriginAllowed($origin) {
        if (empty($origin)) {
            return false;
        }

        if (in_array('*', $this->allowedOrigins)) {
            return true;
        }

        return in_array($origin, $this->allowedOrigins);
    }

    public static function create($config = []) {
        return new static($config);
    }
}
