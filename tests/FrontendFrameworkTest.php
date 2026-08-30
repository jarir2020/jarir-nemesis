<?php
declare(strict_types=1);

use Nemesis\Http\Middleware\FrontendFrameworkMiddleware;
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
        mkdir($this->tmpRoot . '/resources/js/views', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/react', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/vue', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/nuxt', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/svelte', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/angular', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/preact', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/solid', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/remix', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/astro', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/qwik', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/lit', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/ember', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/sveltekit', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/inertia', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/livewire', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/htmx', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/jquery', 0775, true);
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
            'allow' => [
                'server', 'react', 'vue', 'next', 'ghost', 'alpine',
                'nuxt', 'svelte', 'angular', 'preact', 'solid', 'remix',
                'astro', 'qwik', 'lit', 'ember', 'sveltekit', 'inertia',
                'livewire', 'htmx', 'jquery',
            ],
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
                'nuxt' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/nuxt',
                    'entry' => $this->tmpRoot . '/resources/js/nuxt/app.js',
                    'build' => $this->tmpRoot . '/public/build/nuxt',
                    'manifest' => $this->tmpRoot . '/public/build/nuxt/manifest.json',
                    'compiler' => 'vite',
                    'middleware' => 'framework:nuxt',
                    'fallback' => false,
                ],
                'svelte' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/svelte',
                    'entry' => $this->tmpRoot . '/resources/js/svelte/app.js',
                    'build' => $this->tmpRoot . '/public/build/svelte',
                    'manifest' => $this->tmpRoot . '/public/build/svelte/manifest.json',
                    'compiler' => 'vite',
                    'middleware' => 'framework:svelte',
                    'fallback' => false,
                ],
                'angular' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/angular',
                    'entry' => $this->tmpRoot . '/resources/js/angular/app.js',
                    'build' => $this->tmpRoot . '/public/build/angular',
                    'manifest' => $this->tmpRoot . '/public/build/angular/manifest.json',
                    'compiler' => 'webpack',
                    'middleware' => 'framework:angular',
                    'fallback' => false,
                ],
                'preact' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/preact',
                    'entry' => $this->tmpRoot . '/resources/js/preact/app.js',
                    'build' => $this->tmpRoot . '/public/build/preact',
                    'manifest' => $this->tmpRoot . '/public/build/preact/manifest.json',
                    'compiler' => 'vite',
                    'middleware' => 'framework:preact',
                    'fallback' => false,
                ],
                'solid' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/solid',
                    'entry' => $this->tmpRoot . '/resources/js/solid/app.js',
                    'build' => $this->tmpRoot . '/public/build/solid',
                    'manifest' => $this->tmpRoot . '/public/build/solid/manifest.json',
                    'compiler' => 'vite',
                    'middleware' => 'framework:solid',
                    'fallback' => false,
                ],
                'remix' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/remix',
                    'entry' => $this->tmpRoot . '/resources/js/remix/app.js',
                    'build' => $this->tmpRoot . '/public/build/remix',
                    'manifest' => $this->tmpRoot . '/public/build/remix/manifest.json',
                    'compiler' => 'vite',
                    'middleware' => 'framework:remix',
                    'fallback' => false,
                ],
                'astro' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/astro',
                    'entry' => $this->tmpRoot . '/resources/js/astro/app.js',
                    'build' => $this->tmpRoot . '/public/build/astro',
                    'manifest' => $this->tmpRoot . '/public/build/astro/manifest.json',
                    'compiler' => 'vite',
                    'middleware' => 'framework:astro',
                    'fallback' => false,
                ],
                'qwik' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/qwik',
                    'entry' => $this->tmpRoot . '/resources/js/qwik/app.js',
                    'build' => $this->tmpRoot . '/public/build/qwik',
                    'manifest' => $this->tmpRoot . '/public/build/qwik/manifest.json',
                    'compiler' => 'vite',
                    'middleware' => 'framework:qwik',
                    'fallback' => false,
                ],
                'lit' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/lit',
                    'entry' => $this->tmpRoot . '/resources/js/lit/app.js',
                    'build' => $this->tmpRoot . '/public/build/lit',
                    'manifest' => $this->tmpRoot . '/public/build/lit/manifest.json',
                    'compiler' => 'vite',
                    'middleware' => 'framework:lit',
                    'fallback' => false,
                ],
                'ember' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/ember',
                    'entry' => $this->tmpRoot . '/resources/js/ember/app.js',
                    'build' => $this->tmpRoot . '/public/build/ember',
                    'manifest' => $this->tmpRoot . '/public/build/ember/manifest.json',
                    'compiler' => 'ember-cli',
                    'middleware' => 'framework:ember',
                    'fallback' => false,
                ],
                'sveltekit' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/sveltekit',
                    'entry' => $this->tmpRoot . '/resources/js/sveltekit/app.js',
                    'build' => $this->tmpRoot . '/public/build/sveltekit',
                    'manifest' => $this->tmpRoot . '/public/build/sveltekit/manifest.json',
                    'compiler' => 'vite',
                    'middleware' => 'framework:sveltekit',
                    'fallback' => false,
                ],
                'inertia' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/inertia',
                    'entry' => $this->tmpRoot . '/resources/js/inertia/app.js',
                    'build' => $this->tmpRoot . '/public/build/inertia',
                    'manifest' => $this->tmpRoot . '/public/build/inertia/manifest.json',
                    'compiler' => 'vite',
                    'middleware' => 'framework:inertia',
                    'fallback' => false,
                ],
                'livewire' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/livewire',
                    'entry' => $this->tmpRoot . '/resources/js/livewire/app.js',
                    'build' => $this->tmpRoot . '/public/build/livewire',
                    'manifest' => $this->tmpRoot . '/public/build/livewire/manifest.json',
                    'compiler' => 'server',
                    'middleware' => 'framework:livewire',
                    'fallback' => false,
                ],
                'htmx' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/htmx',
                    'entry' => $this->tmpRoot . '/resources/js/htmx/app.js',
                    'build' => $this->tmpRoot . '/public/build/htmx',
                    'manifest' => $this->tmpRoot . '/public/build/htmx/manifest.json',
                    'compiler' => 'server',
                    'middleware' => 'framework:htmx',
                    'fallback' => false,
                ],
                'jquery' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/jquery',
                    'entry' => $this->tmpRoot . '/resources/js/jquery/app.js',
                    'build' => $this->tmpRoot . '/public/build/jquery',
                    'manifest' => $this->tmpRoot . '/public/build/jquery/manifest.json',
                    'compiler' => 'vite',
                    'middleware' => 'framework:jquery',
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
        $this->assertSame($this->tmpRoot . '/resources/views/nuxt', $manager->frameworkViewPath('nuxt'));
        $this->assertSame($this->tmpRoot . '/resources/views/svelte', $manager->frameworkViewPath('svelte'));
        $this->assertSame($this->tmpRoot . '/resources/views/angular', $manager->frameworkViewPath('angular'));
        $this->assertSame($this->tmpRoot . '/resources/views/preact', $manager->frameworkViewPath('preact'));
        $this->assertSame($this->tmpRoot . '/resources/views/solid', $manager->frameworkViewPath('solid'));
        $this->assertSame($this->tmpRoot . '/resources/views/remix', $manager->frameworkViewPath('remix'));
        $this->assertSame($this->tmpRoot . '/resources/views/astro', $manager->frameworkViewPath('astro'));
        $this->assertSame($this->tmpRoot . '/resources/views/qwik', $manager->frameworkViewPath('qwik'));
        $this->assertSame($this->tmpRoot . '/resources/views/lit', $manager->frameworkViewPath('lit'));
        $this->assertSame($this->tmpRoot . '/resources/views/ember', $manager->frameworkViewPath('ember'));
        $this->assertSame($this->tmpRoot . '/resources/views/sveltekit', $manager->frameworkViewPath('sveltekit'));
        $this->assertSame($this->tmpRoot . '/resources/views/inertia', $manager->frameworkViewPath('inertia'));
        $this->assertSame($this->tmpRoot . '/resources/views/livewire', $manager->frameworkViewPath('livewire'));
        $this->assertSame($this->tmpRoot . '/resources/views/htmx', $manager->frameworkViewPath('htmx'));
        $this->assertSame($this->tmpRoot . '/resources/views/jquery', $manager->frameworkViewPath('jquery'));
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

    public function testAdditionalFrameworksAreAllowed(): void
    {
        foreach ([
            'nuxt', 'svelte', 'angular', 'preact', 'solid', 'remix',
            'astro', 'qwik', 'lit', 'ember', 'sveltekit', 'inertia',
            'livewire', 'htmx', 'jquery',
        ] as $framework) {
            $this->assertTrue(FrontendManager::getInstance()->isAllowed($framework), $framework . ' should be allowed');
            $this->assertTrue(FrontendManager::getInstance()->supportsFramework($framework), $framework . ' should be supported');
        }
    }

    public function testFrontendGroupAcceptsExpandedFrameworkNames(): void
    {
        $router = new Router();
        $captured = null;

        $router->frontendGroup('astro', 'layouts.app', function (Router $router) use (&$captured): void {
            $router->get('/astro-home', function (Request $request) use (&$captured): Response {
                $captured = [
                    'framework' => $request->getMeta('frontend.framework'),
                    'layout' => $request->getMeta('frontend.layout'),
                ];

                return Response::make('astro-ok');
            });
        });

        $response = $router->dispatch('/astro-home', 'GET');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('astro-ok', $response->getContent());
        $this->assertSame('astro', $captured['framework']);
        $this->assertSame('layouts.app', $captured['layout']);
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
