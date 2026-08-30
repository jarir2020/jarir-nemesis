<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 3 — MiddlewareInterface | Moved from src/Middleware: 2026-04-02

namespace Nemesis\Http\Middleware;

use Nemesis\Contracts\MiddlewareInterface;
use Nemesis\Http\Request;
use Nemesis\Http\Response;

class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /** @var array<string, string> */
    protected array $headers = [
        'X-Content-Type-Options'    => 'nosniff',
        'X-Frame-Options'           => 'SAMEORIGIN',
        'X-XSS-Protection'          => '1; mode=block',
        'Referrer-Policy'           => 'strict-origin-when-cross-origin',
        'Content-Security-Policy'   => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:;",
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
        'Permissions-Policy'        => 'geolocation=(), microphone=(), camera=()',
    ];

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        foreach ($this->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
