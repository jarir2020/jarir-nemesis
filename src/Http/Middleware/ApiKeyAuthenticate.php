<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 16 — API Key middleware | 2026-04-06

namespace Nemesis\Http\Middleware;

use Nemesis\Auth\ApiKey;
use Nemesis\Contracts\MiddlewareInterface;
use Nemesis\Http\Request;
use Nemesis\Http\Response;

/**
 * ApiKeyAuthenticate — validates X-Api-Key header on protected routes.
 *
 * Route usage:
 *   ->middleware('api.key')           // any valid key
 *   ->middleware('api.key:write')     // key must have 'write' scope
 */
class ApiKeyAuthenticate implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, string $scope = ''): Response
    {
        $rawKey = $request->header('X-Api-Key');

        if (!$rawKey) {
            return Response::json(['error' => 'API key required. Set X-Api-Key header.'], 401);
        }

        $keyRow = ApiKey::verify((string) $rawKey);

        if (!$keyRow) {
            return Response::json(['error' => 'Invalid or expired API key.'], 401);
        }

        // Optional scope guard
        if ($scope !== '' && !ApiKey::hasScope($keyRow, $scope)) {
            return Response::json(['error' => "Forbidden. Key missing scope: {$scope}."], 403);
        }

        // Attach key row to request for downstream use
        $request->setMeta('api_key', $keyRow);

        return $next($request);
    }
}
