<?php
declare(strict_types=1);

namespace Nemesis\Http\Middleware;

use Nemesis\Contracts\MiddlewareInterface;
use Nemesis\Frontend\FrontendManager;
use Nemesis\Http\Request;
use Nemesis\Http\Response;

/**
 * Selects the frontend framework for the current request.
 *
 * Route usage:
 *   ->middleware('framework:react')
 *   ->middleware('framework:vue')
 *   ->middleware('framework:server')
 */
class FrontendFrameworkMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next, string $framework = ''): Response
    {
        $manager = FrontendManager::getInstance();
        $selected = $framework !== '' ? $framework : $manager->defaultFramework();

        try {
            $manager->setCurrentFramework($selected, [
                'route_framework' => $selected,
            ]);
        } catch (\InvalidArgumentException $e) {
            return Response::json([
                'error' => $e->getMessage(),
            ], 404);
        }

        $request->setMeta('frontend.framework', $manager->currentFramework());
        $request->setMeta('frontend.view_path', $manager->currentViewPath());
        $request->setMeta('frontend.entry', $manager->currentEntry());
        $request->setMeta('frontend.build_path', $manager->currentBuildPath());
        $request->setMeta('frontend.manifest_path', $manager->currentManifestPath());
        $request->setMeta('frontend.compiler', $manager->currentCompiler());

        try {
            return $next($request);
        } finally {
            $manager->clearCurrentFramework();
        }
    }

    public function terminate(Request $request, Response $response): void
    {
        FrontendManager::getInstance()->clearCurrentFramework();
    }
}
