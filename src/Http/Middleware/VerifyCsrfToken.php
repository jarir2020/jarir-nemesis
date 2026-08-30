<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 3 — MiddlewareInterface | Updated: 2026-04-02

namespace Nemesis\Http\Middleware;

use Nemesis\Contracts\MiddlewareInterface;
use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Http\Session;

class VerifyCsrfToken implements MiddlewareInterface
{
    /**
     * URI prefixes/paths excluded from CSRF verification.
     * Supports exact match and '*' wildcard suffix.
     *
     * @var string[]
     */
    protected array $except = [
        // 'api/webhooks/*',
    ];

    public function handle(Request $request, callable $next): Response
    {
        if ($this->isReading() || $this->inExceptArray() || $this->tokensMatch()) {
            return $next($request);
        }

        return Response::json(['error' => 'CSRF token mismatch.'], 419);
    }

    protected function isReading(): bool
    {
        return in_array(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            ['HEAD', 'GET', 'OPTIONS'],
            true
        );
    }

    protected function inExceptArray(): bool
    {
        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        foreach ($this->except as $pattern) {
            if (str_ends_with($pattern, '*')) {
                if (str_starts_with((string) $uri, rtrim($pattern, '*'))) {
                    return true;
                }
            } elseif ($pattern === $uri) {
                return true;
            }
        }
        return false;
    }

    protected function tokensMatch(): bool
    {
        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return is_string($token) && hash_equals(Session::token(), $token);
    }
}
