<?php

namespace Nemesis\Middleware;

class SecurityHeadersMiddleware {
    protected $headers = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:;",
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
    ];

    public function handle($request, $next) {
        $response = $next($request);

        // Ensure headers are sent
        if (!headers_sent()) {
            foreach ($this->headers as $key => $value) {
                header("$key: $value");
            }
        }

        return $response;
    }
}
