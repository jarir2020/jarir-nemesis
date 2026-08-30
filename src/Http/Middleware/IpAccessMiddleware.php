<?php
declare(strict_types=1);

namespace Nemesis\Http\Middleware;

use Nemesis\Contracts\MiddlewareInterface;
use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Support\IpAccess;

class IpAccessMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $policy = IpAccess::fromConfig();
        $ip = $policy->clientIp($request);

        if (!$policy->isAllowed($ip)) {
            return Response::json([
                'error' => 'Access denied.',
                'ip' => $ip,
            ], 403);
        }

        $request = $request->withAttribute('security.ip', $ip);
        return $next($request);
    }
}
