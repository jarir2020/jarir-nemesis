<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 3 — MiddlewareInterface | Updated: 2026-04-02

namespace Nemesis\Http\Middleware;

use Nemesis\Contracts\MiddlewareInterface;
use Nemesis\Http\Request;
use Nemesis\Http\Response;

class TestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $logFile = __DIR__ . '/../../../../storage/framework/middleware_test.log';
        @file_put_contents($logFile, "Passed through TestMiddleware at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

        $response = $next($request);

        @file_put_contents($logFile, "Returning from TestMiddleware at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

        return $response;
    }
}
