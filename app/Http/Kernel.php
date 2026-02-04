<?php

namespace App\Http;

class Kernel {
    /**
     * The application's global HTTP middleware stack.
     * These middleware are run during every request to your application.
     */
    protected $middleware = [
        \App\Http\Middleware\CheckForMaintenanceMode::class,
        \App\Http\Middleware\StartSession::class,
        \App\Http\Middleware\VerifyCsrfToken::class,
    ];

    /**
     * The application's route middleware groups.
     */
    protected $middlewareGroups = [
        'api' => [
            // \App\Http\Middleware\Authenticate::class,
        ],
    ];

    /**
     * The application's route middleware.
     * These middleware may be assigned to groups or used individually.
     */
    protected $routeMiddleware = [
        'throttle' => \App\Http\Middleware\ThrottleRequests::class,
    ];

    /**
     * Get the global middleware.
     */
    public function getMiddleware() {
        return $this->middleware;
    }

    /**
     * Get the middleware groups.
     */
    public function getMiddlewareGroups() {
        return $this->middlewareGroups;
    }

    /**
     * Get the route middleware.
     */
    public function getRouteMiddleware() {
        return $this->routeMiddleware;
    }
}
