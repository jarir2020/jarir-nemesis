<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 21 — Multi-tenancy: TenantMiddleware | 2026-04-06

namespace Nemesis\Http\Middleware;

use Nemesis\Contracts\MiddlewareInterface;
use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Tenancy\TenantManager;

/**
 * TenantMiddleware — resolves the current tenant from the incoming request
 * and sets it in TenantManager before the controller runs.
 *
 * Route registration (add to your route group or global stack):
 *   ->middleware('tenant')          // fails silently (returns null tenant) if not resolved
 *   ->middleware('tenant:required') // returns 404 if no tenant resolved
 *
 * The middleware reads from (in configured priority order):
 *   - Subdomain:  acme.myapp.com
 *   - Header:     X-Tenant-ID: acme
 *   - Path:       /acme/dashboard
 *   - Query:      ?tenant=acme
 */
class TenantMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, string $mode = ''): Response
    {
        $host    = $request->header('host') ?: ($request->server('HTTP_HOST') ?? '');
        $path    = $request->server('REQUEST_URI') ?? $request->path();
        $headers = $this->normalizeHeaders($request->headers());
        $query   = $request->query();

        $tenant = TenantManager::identify(
            host:    (string) $host,
            path:    (string) $path,
            headers: $headers,
            query:   is_array($query) ? $query : [],
        );

        if ($tenant === null && $mode === 'required') {
            return Response::json(['error' => 'Tenant not found.'], 404);
        }

        return $next($request);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower((string) $key)] = $value;
        }
        return $normalized;
    }
}
