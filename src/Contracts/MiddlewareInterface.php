<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Created: 2026-04-02
namespace Nemesis\Contracts;

use Nemesis\Http\Request;
use Nemesis\Http\Response;

/**
 * Stable public contract for all Nemesis middleware.
 *
 * Before logic:  place code before $next($request)
 * After logic:   act on $response returned by $next($request)
 * Short-circuit: return a Response directly without calling $next
 */
interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
