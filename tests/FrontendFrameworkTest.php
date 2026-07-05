<?php
declare(strict_types=1);

use App\Http\Middleware\FrontendFrameworkMiddleware;
use App\Controllers\FrontendController;
use Nemesis\Frontend\FrontendManager;
use Nemesis\Core\View;
use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Router\Router;
use Nemesis\Testing\TestCase;

class FrontendFrameworkTest extends TestCase
{
    private string $tmpRoot;

    public function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/nemesis-frontend-' . uniqid('', true);
        mkdir($this->tmpRoot . '/resources/views/react', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/vue', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/server', 0775, true);
        mkdir($this->tmpRoot . '/views', 0775, true);
        mkdir($this->tmpRoot . '/views/layouts', 0775, true);
        View::addPath(base_path('views'));

        file_put_contents(
            $this->tmpRoot . '/resources/views/react/login.blade.php',
            <<<'BLADE'
@extends($layout ?? 'layouts.app')

@section('content')
<section class="frontend-shell react-shell">
    <h1>React Login</h1>
    <p>Framework: {{ $framework }}</p>

    <form method="POST" action="/login">
        @csrf
        <label>
            Email
            <input type="email" name="email" autocomplete="email" required>
        </label>

        <label>
            Password
            <input type="password" name="password" autocomplete="current-password" required>
        </label>

        <button type="submit">Sign in</button>
    </form>
</section>
@endsection
BLADE
        );

        file_put_contents(
            $this->tmpRoot . '/resources/views/vue/dashboard.blade.php',
            'Vue dashboard for {{ $framework }}'
        );

        file_put_contents(
            $this->tmpRoot . '/views/home.blade.php',
            'Server home for {{ $name }}'
        );

        FrontendManager::boot([
            'default' => 'server',
            'allow' => ['server', 'react', 'vue'],
            'frameworks' => [
                'server' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/views',
                    'entry' => null,
                    'build' => null,
                    'manifest' => null,
                    'compiler' => 'server',
                    'fallback' => true,
                ],
                'react' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/react',
                    'entry' => $this->tmpRoot . '/resources/js/react/app.js',
                    'build' => $this->tmpRoot . '/public/build/react',
                    'manifest' => $this->tmpRoot . '/public/build/react/manifest.json',
                    'compiler' => 'vite',
                    'middleware' => 'framework:react',
                    'fallback' => false,
                ],
                'vue' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/vue',
                    'entry' => $this->tmpRoot . '/resources/js/vue/app.js',
                    'build' => $this->tmpRoot . '/public/build/vue',
                    'manifest' => $this->tmpRoot . '/public/build/vue/manifest.json',
                    'compiler' => 'vite',
                    'middleware' => 'framework:vue',
                    'fallback' => false,
                ],
            ],
            'runtime' => [],
        ]);
    }

    public function tearDown(): void
    {
        FrontendManager::flush();
        $this->deleteTree($this->tmpRoot);
    }

    public function testFrontendManagerResolvesFrameworkPaths(): void
    {
        $manager = FrontendManager::getInstance();
        $this->assertSame($this->tmpRoot . '/resources/views/react', $manager->frameworkViewPath('react'));
        $this->assertSame($this->tmpRoot . '/resources/js/react/app.js', $manager->frameworkEntry('react'));
        $this->assertSame($this->tmpRoot . '/public/build/react', $manager->frameworkBuildPath('react'));
        $this->assertSame($this->tmpRoot . '/public/build/react/manifest.json', $manager->frameworkManifestPath('react'));
    }

    public function testMiddlewareSetsAndClearsCurrentFramework(): void
    {
        $middleware = new FrontendFrameworkMiddleware();
        $request    = new Request();

        $response = $middleware->handle($request, function (Request $handledRequest): Response {
            $this->assertSame('react', $handledRequest->getMeta('frontend.framework'));
            $this->assertSame('vite', $handledRequest->getMeta('frontend.compiler'));
            return Response::make('ok');
        }, 'react');

        $this->assertSame('ok', $response->getContent());
        $middleware->terminate($request, $response);
        $this->assertNull(FrontendManager::getInstance()->currentFramework());
    }

    public function testViewEngineUsesCurrentFrameworkViewDirectory(): void
    {
        FrontendManager::getInstance()->setCurrentFramework('react');

        $html = View::make('login', ['framework' => 'react']);

        $this->assertStringContainsString('<form method="POST" action="/login">', $html);
        $this->assertStringContainsString('class="app-nav"', $html);
    }

    public function testServerViewFallsBackToServerViews(): void
    {
        FrontendManager::getInstance()->setCurrentFramework('server');

        $html = View::make('home', ['name' => 'Amina']);

        $this->assertStringContainsString('Server home for Amina', $html);
    }

    public function testFrontendControllerRendersFrameworkSpecificView(): void
    {
        $controller = new FrontendController();
        $request = new Request();
        $request->setMeta('frontend.framework', 'vue');
        FrontendManager::getInstance()->setCurrentFramework('vue');

        ob_start();
        $controller->dashboard($request);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Vue dashboard for vue', $html);
        $this->assertStringContainsString('vue', $html);
    }

    public function testAuthenticatedLayoutShowsAdminLinks(): void
    {
        $controller = new FrontendController();
        $request = new Request();
        $request->setMeta('frontend.framework', 'server');
        $request->setMeta('auth', ['sub' => 1, 'role' => 'admin']);
        FrontendManager::getInstance()->setCurrentFramework('server');

        ob_start();
        $controller->admin($request);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Admin', $html);
        $this->assertStringContainsString('Logout', $html);
        $this->assertStringContainsString('Signed in', $html);
    }

    public function testFrontendGroupSeedsFrameworkAndLayoutMetadata(): void
    {
        $router = new Router();
        $captured = null;

        $router->frontendGroup('react', 'layouts.app', function (Router $router) use (&$captured): void {
            $router->get('/group-meta', function (Request $request) use (&$captured): Response {
                $captured = [
                    'framework' => $request->getMeta('frontend.framework'),
                    'layout' => $request->getMeta('frontend.layout'),
                    'route_framework' => $request->getMeta('route.framework'),
                    'route_layout' => $request->getMeta('route.layout'),
                ];

                return Response::make('ok');
            });
        });

        $result = $router->dispatch('/group-meta', 'GET');

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame('ok', $result->getContent());
        $this->assertSame('react', $captured['framework']);
        $this->assertSame('layouts.app', $captured['layout']);
        $this->assertSame('react', $captured['route_framework']);
        $this->assertSame('layouts.app', $captured['route_layout']);
    }

    public function testLoginViewContainsRealPostForm(): void
    {
        FrontendManager::getInstance()->setCurrentFramework('react');

        $html = View::make('login', ['framework' => 'react']);

        $this->assertStringContainsString('class="app-nav"', $html);
        $this->assertStringContainsString('Guest', $html);
        $this->assertStringContainsString('Login', $html);
        $this->assertStringContainsString('<form method="POST" action="/login">', $html);
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('name="password"', $html);
        $this->assertStringContainsString('_token', $html);
    }

    private function deleteTree(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $path = $item->getPathname();
            if ($item->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
