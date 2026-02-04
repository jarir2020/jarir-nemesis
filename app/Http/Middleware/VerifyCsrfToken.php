<?php

namespace App\Http\Middleware;

use Nemesis\Http\Session;

class VerifyCsrfToken {
    /**
     * The URIs that should be excluded from CSRF verification.
     */
    protected $except = [
        // 'api/webhooks/*',
    ];

    public function handle($request, $next) {
        if ($this->isReading($request) || $this->inExceptArray($request) || $this->tokensMatch($request)) {
            return $next($request);
        }

        header('HTTP/1.1 419 Page Expired');
        echo json_encode(['error' => 'CSRF token mismatch.']);
        exit;
    }

    protected function isReading($request) {
        return in_array($_SERVER['REQUEST_METHOD'], ['HEAD', 'GET', 'OPTIONS']);
    }

    protected function inExceptArray($request) {
        // Simple URI matching logic
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        foreach ($this->except as $except) {
            if ($except === $uri) return true;
        }
        return false;
    }

    protected function tokensMatch($request) {
        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return is_string($token) && hash_equals(Session::token(), $token);
    }
}
