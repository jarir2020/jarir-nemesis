<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 3 — MiddlewareInterface | Updated: 2026-04-02

namespace Nemesis\Http\Middleware;

use Nemesis\Contracts\MiddlewareInterface;
use Nemesis\Core\Cache;
use Nemesis\Http\Request;
use Nemesis\Http\Response;

class ThrottleRequests implements MiddlewareInterface
{
    /**
     * Extra optional parameters are valid in interface implementations (PHP LSP).
     *
     * @param  int|string  $maxAttempts   Max requests per window (default 60).
     * @param  int|string  $decayMinutes  Window length in minutes (default 1).
     */
    public function handle(
        Request  $request,
        callable $next,
        int|string $maxAttempts  = 60,
        int|string $decayMinutes = 1,
    ): Response {
        $maxAttempts  = (int) $maxAttempts;
        $decayMinutes = (int) $decayMinutes;

        $key  = $this->resolveRequestSignature();
        $hits = (int) Cache::get($key, 0);

        if ($hits >= $maxAttempts) {
            return Response::json(
                ['error' => 'Too many requests. Please slow down.'],
                429
            )
            ->withHeader('X-RateLimit-Limit',     (string) $maxAttempts)
            ->withHeader('X-RateLimit-Remaining', '0')
            ->withHeader('Retry-After',            (string) ($decayMinutes * 60));
        }

        Cache::set($key, $hits + 1, $decayMinutes * 60);

        $response = $next($request);

        return $response
            ->withHeader('X-RateLimit-Limit',     (string) $maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $maxAttempts - ($hits + 1)));
    }

    protected function resolveRequestSignature(): string
    {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        return 'throttle:' . md5($ip . '|' . $uri);
    }
}
