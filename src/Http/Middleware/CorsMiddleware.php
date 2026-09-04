<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 3 — MiddlewareInterface | Moved from src/Middleware: 2026-04-02

namespace Nemesis\Http\Middleware;

use Nemesis\Contracts\MiddlewareInterface;
use Nemesis\Http\Request;
use Nemesis\Http\Response;

class CorsMiddleware implements MiddlewareInterface
{
    protected array  $allowedOrigins;
    protected array  $allowedMethods;
    protected array  $allowedHeaders;
    protected bool   $allowCredentials;
    protected int    $maxAge;

    public function __construct(array $config = [])
    {
        if ($config === [] && function_exists('config')) {
            $config = (array) \config('cors', []);
        }

        $this->allowedOrigins   = $config['origins']     ?? ['*'];
        $this->allowedMethods   = $config['methods']     ?? ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        $this->allowedHeaders   = $config['headers']     ?? ['Content-Type', 'Authorization', 'X-Requested-With'];
        $this->allowCredentials = $config['credentials'] ?? false;
        $this->maxAge           = $config['max_age']     ?? 86400;
    }

    public function handle(Request $request, callable $next): Response
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowed = $this->isOriginAllowed($origin);

        // Preflight OPTIONS — respond immediately without calling $next
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            if (!$allowed) {
                return Response::make('', 403);
            }

            return $this->addCorsHeaders(Response::make('', 204), $origin, true);
        }

        return $allowed
            ? $this->addCorsHeaders($next($request), $origin)
            : $next($request);
    }

    protected function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) return false;
        if (in_array('*', $this->allowedOrigins, true)) return true;
        return in_array($origin, $this->allowedOrigins, true);
    }

    protected function addCorsHeaders(Response $response, string $origin, bool $preflight = false): Response
    {
        $wildcard = in_array('*', $this->allowedOrigins, true) && !$this->allowCredentials;
        $response = $response->withHeader('Access-Control-Allow-Origin', $wildcard ? '*' : $origin)
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Expose-Headers', 'Content-Length, X-JSON');

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        if ($preflight) {
            $response = $response
                ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
                ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
                ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
        }

        return $response;
    }

    public static function create(array $config = []): static
    {
        return new static($config);
    }
}
