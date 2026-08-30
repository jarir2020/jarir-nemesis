<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 16 — JWT Bearer auth middleware | 2026-04-06

namespace Nemesis\Http\Middleware;

use Nemesis\Auth\JWT;
use Nemesis\Contracts\MiddlewareInterface;
use Nemesis\Http\Request;
use Nemesis\Http\Response;

/**
 * Authenticate — validates a JWT Bearer token on protected routes.
 *
 * Route usage:
 *   ->middleware('auth')          // requires valid JWT
 *   ->middleware('auth:admin')    // requires JWT with role=admin
 */
class Authenticate implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, string $role = ''): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return Response::json(['error' => 'Unauthenticated. Bearer token required.'], 401);
        }

        $payload = JWT::verify($token);

        if (!$payload) {
            return Response::json(['error' => 'Invalid or expired token.'], 401);
        }

        // Optional role guard
        if ($role !== '' && ($payload['role'] ?? '') !== $role) {
            return Response::json(['error' => 'Forbidden. Insufficient role.'], 403);
        }

        // Attach payload to request for downstream use
        $request->setMeta('auth', $payload);

        return $next($request);
    }
}
