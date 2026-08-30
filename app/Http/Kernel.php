<?php
declare(strict_types=1);

// Nemesis 7.1.1 | Phase 16 — auth + api.key middleware registered | Updated: 2026-08-30
// v7.1.1 (Gap 8): middleware classes moved to Nemesis\Http\Middleware namespace
// v7.1.1 (Gap 11): removed StartSession + VerifyCsrfToken from the global
//                   middleware list (they live in the 'web' group).

namespace App\Http;

use Nemesis\Http\Middleware\ApiKeyAuthenticate;
use Nemesis\Http\Middleware\ApiVersionMiddleware;
use Nemesis\Http\Middleware\Authenticate;
use Nemesis\Http\Middleware\CheckForMaintenanceMode;
use Nemesis\Http\Middleware\CorsMiddleware;
use Nemesis\Http\Middleware\DebugBarMiddleware;
use Nemesis\Http\Middleware\FrontendFrameworkMiddleware;
use Nemesis\Http\Middleware\IpAccessMiddleware;
use Nemesis\Http\Middleware\SecurityHeadersMiddleware;
use Nemesis\Http\Middleware\StartSession;
use Nemesis\Http\Middleware\ThrottleRequests;
use Nemesis\Http\Middleware\VerifyCsrfToken;

class Kernel
{
    /**
     * Global HTTP middleware — runs on every request.
     *
     * v7.1.1 (Gap 11): session and CSRF are no longer global. They live in
     * the 'web' group, and routes outside that group opt in via the
     * 'session' / 'csrf' aliases.
     *
     * @var class-string[]
     */
    protected array $middleware = [
        \Nemesis\Http\Middleware\CheckForMaintenanceMode::class,
    ];

    /**
     * Middleware groups — assign to route groups with ->group(['middleware' => 'web']).
     *
     * 'web'  — standard browser session stack
     * 'api'  — stateless JSON stack
     *
     * @var array<string, class-string[]|string[]>
     */
    protected array $middlewareGroups = [
        'web' => [
            \Nemesis\Http\Middleware\StartSession::class,
            \Nemesis\Http\Middleware\VerifyCsrfToken::class,
        ],
        'api' => [
            'throttle:60,1',
        ],
    ];

    /**
     * Route middleware aliases — short names used in ->middleware('alias').
     *
     * v7.1.1 (Gap 8): all built-in middleware now lives in
     * Nemesis\Http\Middleware. App-level overrides can be added here.
     *
     * @var array<string, class-string>
     */
    protected array $routeMiddleware = [
        'throttle'    => ThrottleRequests::class,
        'csrf'        => VerifyCsrfToken::class,
        'session'     => StartSession::class,
        'auth'        => Authenticate::class,
        'api.key'     => ApiKeyAuthenticate::class,
        'cors'        => CorsMiddleware::class,
        'security'    => SecurityHeadersMiddleware::class,
        'api.version' => ApiVersionMiddleware::class,
        'debugbar'    => DebugBarMiddleware::class,
        'framework'   => FrontendFrameworkMiddleware::class,
        'ip'          => IpAccessMiddleware::class,
    ];

    // -------------------------------------------------------------------------
    // Accessors (used by Router::resolveMiddleware)
    // -------------------------------------------------------------------------

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    public function getRouteMiddleware(): array
    {
        return $this->routeMiddleware;
    }
}
