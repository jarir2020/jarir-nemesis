<?php
declare(strict_types=1);

// Nemesis 6.2.1 | Phase 16 — auth + api.key middleware registered | Updated: 2026-04-06

namespace App\Http;

class Kernel
{
    /**
     * Global HTTP middleware — runs on every request.
     *
     * @var class-string[]
     */
    protected array $middleware = [
        \App\Http\Middleware\CheckForMaintenanceMode::class,
        \App\Http\Middleware\StartSession::class,
        \App\Http\Middleware\VerifyCsrfToken::class,
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
            \App\Http\Middleware\StartSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
        ],
        'api' => [
            'throttle:60,1',
        ],
    ];

    /**
     * Route middleware aliases — short names used in ->middleware('alias').
     *
     * @var array<string, class-string>
     */
    protected array $routeMiddleware = [
        // app/Http/Middleware
        'throttle' => \App\Http\Middleware\ThrottleRequests::class,
        'csrf'     => \App\Http\Middleware\VerifyCsrfToken::class,
        'session'  => \App\Http\Middleware\StartSession::class,
        'auth'    => \App\Http\Middleware\Authenticate::class,
        'api.key' => \App\Http\Middleware\ApiKeyAuthenticate::class,
        // 'guest'  => \App\Http\Middleware\RedirectIfAuthenticated::class,
        // 'role'   => \App\Http\Middleware\CheckRole::class,

        // Consolidated from src/Middleware → app/Http/Middleware (2026-04-02)
        'cors'        => \App\Http\Middleware\CorsMiddleware::class,
        'security'    => \App\Http\Middleware\SecurityHeadersMiddleware::class,
        'api.version' => \App\Http\Middleware\ApiVersionMiddleware::class,
        'debugbar'    => \App\Http\Middleware\DebugBarMiddleware::class,
        'framework'   => \App\Http\Middleware\FrontendFrameworkMiddleware::class,
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
