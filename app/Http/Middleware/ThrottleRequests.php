<?php

namespace App\Http\Middleware;

use Nemesis\Core\Cache;
use Nemesis\Http\Response;

class ThrottleRequests {
    /**
     * Handle the incoming request.
     */
    public function handle($request, $next, $maxAttempts = 60, $decayMinutes = 1) {
        $key = $this->resolveRequestSignature($request);
        $maxAttempts = (int) $maxAttempts;

        $hits = Cache::get($key, 0);

        if ($hits >= $maxAttempts) {
            header('X-RateLimit-Limit: ' . $maxAttempts);
            header('X-RateLimit-Remaining: 0');
            header('Retry-After: ' . ($decayMinutes * 60));
            
            header('HTTP/1.1 429 Too Many Requests');
            die(json_encode(['error' => 'Too many requests. Please slow down.']));
        }

        Cache::set($key, $hits + 1, $decayMinutes * 60);

        header('X-RateLimit-Limit: ' . $maxAttempts);
        header('X-RateLimit-Remaining: ' . ($maxAttempts - ($hits + 1)));

        return $next($request);
    }

    /**
     * Resolve the request signature (IP + URI).
     */
    protected function resolveRequestSignature($request) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        return 'throttle:' . md5($ip . '|' . $uri);
    }
}
