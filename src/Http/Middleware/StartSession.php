<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 3 — MiddlewareInterface | Updated: 2026-04-02

namespace Nemesis\Http\Middleware;

use Nemesis\Contracts\MiddlewareInterface;
use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Http\Session;

class StartSession implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        new Session(); // Ensures session is started
        return $next($request);
    }

    /**
     * Terminable — flush session data after the response is sent.
     * Called by Pipeline::terminate().
     */
    public function terminate(Request $request, Response $response): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
