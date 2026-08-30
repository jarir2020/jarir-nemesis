<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 3 — MiddlewareInterface | Moved from src/Middleware: 2026-04-02

namespace Nemesis\Http\Middleware;

use Nemesis\Contracts\MiddlewareInterface;
use Nemesis\Http\Request;
use Nemesis\Http\Response;

class ApiVersionMiddleware implements MiddlewareInterface
{
    protected string $defaultVersion = 'v1';

    public function __construct(protected ?string $version = null)
    {
        $this->version ??= $this->defaultVersion;
    }

    public function handle(Request $request, callable $next): Response
    {
        $path = $_SERVER['REQUEST_URI'] ?? '';

        if (preg_match('#/api/(v\d+)/#', $path, $matches)) {
            if (!defined('API_VERSION')) define('API_VERSION', $matches[1]);
        } else {
            $headerVersion = $_SERVER['HTTP_API_VERSION'] ?? $_SERVER['HTTP_X_API_VERSION'] ?? null;
            if (!defined('API_VERSION')) define('API_VERSION', $headerVersion ?? $this->defaultVersion);
        }

        return $next($request);
    }

    public static function getVersion(): string
    {
        return defined('API_VERSION') ? API_VERSION : 'v1';
    }
}
