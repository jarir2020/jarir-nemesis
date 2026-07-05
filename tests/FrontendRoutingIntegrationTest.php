<?php
declare(strict_types=1);

use App\Controllers\FrontendController;
use Nemesis\Core\View;
use Nemesis\Frontend\FrontendManager;
use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Router\Router;
use Nemesis\Scaffolder\Scaffolder;
use Nemesis\Testing\TestCase;

class FrontendRoutingIntegrationTest extends TestCase
{
    private string $tmpRoot;

    public function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/nemesis-frontend-integration-' . uniqid('', true);

        mkdir($this->tmpRoot . '/views/layouts', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/react', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/react/layouts', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/vue', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/ghost', 0775, true);
        mkdir($this->tmpRoot . '/resources/views/alpine', 0775, true);
        mkdir($this->tmpRoot . '/views/admin', 0775, true);

        file_put_contents(
            $this->tmpRoot . '/views/layouts/app.blade.php',
            <<<'BLADE'
<!DOCTYPE html>
<html>
<body>
<nav class="integration-shell">
    <span>{{ $framework ?? 'server' }}</span>
    @if(!empty($isAuthenticated))
        <span>Signed in</span>
    @else
        <span>Guest</span>
    @endif
</nav>
@yield('content')
</body>
</html>
BLADE
        );

        file_put_contents(
            $this->tmpRoot . '/resources/views/react/layouts/shell.blade.php',
            <<<'BLADE'
<!DOCTYPE html>
<html>
<body>
<main class="shell-layout">
    @yield('content')
</main>
</body>
</html>
BLADE
        );

        file_put_contents(
            $this->tmpRoot . '/resources/views/react/login.blade.php',
            <<<'BLADE'
@extends($layout ?? 'layouts.app')
@section('content')
<form method="POST" action="/login">
    @csrf
    <input type="email" name="email">
    <input type="password" name="password">
</form>
@endsection
BLADE
        );

        file_put_contents(
            $this->tmpRoot . '/resources/views/vue/dashboard.blade.php',
            <<<'BLADE'
@extends($layout ?? 'layouts.app')
@section('content')
<h1>Vue Dashboard</h1>
@endsection
BLADE
        );

        file_put_contents(
            $this->tmpRoot . '/views/admin/dashboard.blade.php',
            <<<'BLADE'
@extends($layout ?? 'layouts.app')
@section('content')
<h1>Admin Dashboard</h1>
@endsection
BLADE
        );

        FrontendManager::boot([
            'default' => 'server',
            'allow' => ['server', 'react', 'vue', 'ghost', 'alpine'],
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
                    'entry' => null,
                    'build' => null,
                    'manifest' => null,
                    'compiler' => 'vite',
                    'fallback' => false,
                ],
                'vue' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/vue',
                    'entry' => null,
                    'build' => null,
                    'manifest' => null,
                    'compiler' => 'vite',
                    'fallback' => false,
                ],
                'ghost' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/ghost',
                    'entry' => null,
                    'build' => null,
                    'manifest' => null,
                    'compiler' => 'vite',
                    'fallback' => false,
                ],
                'alpine' => [
                    'enabled' => true,
                    'views' => $this->tmpRoot . '/resources/views/alpine',
                    'entry' => null,
                    'build' => null,
                    'manifest' => null,
                    'compiler' => 'vite',
                    'fallback' => false,
                ],
            ],
            'runtime' => [],
        ]);

        View::addPath($this->tmpRoot . '/views');
    }

    public function tearDown(): void
    {
        FrontendManager::flush();
        $this->deleteTree($this->tmpRoot);
    }

    public function testNamedLogoutRouteCanBeGenerated(): void
    {
        $router = new Router();
        $router->post('/logout', fn() => null)->name('logout');

        $this->assertSame('/logout', $router->generate('logout'));
    }

    public function testFrontendGroupProvidesFrameworkLayoutMetadataAndRenders(): void
    {
        $router = new Router();
        $captured = [];

        $router->frontendGroup('react', 'layouts.shell', function (Router $router) use (&$captured): void {
            $router->get('/login', function (Request $request) use (&$captured): Response {
                $captured = [
                    'framework' => $request->getMeta('frontend.framework'),
                    'layout' => $request->getMeta('frontend.layout'),
                    'route_framework' => $request->getMeta('route.framework'),
                    'route_layout' => $request->getMeta('route.layout'),
                ];

                FrontendManager::getInstance()->setCurrentFramework('react');
                $html = View::make('login', [
                    'framework' => $request->getMeta('frontend.framework'),
                    'layout' => $request->getMeta('frontend.layout'),
                    'isAuthenticated' => false,
                ]);

                return Response::make($html);
            });
        });

        $response = $router->dispatch('/login', 'GET');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('shell-layout', $response->getContent());
        $this->assertSame('react', $captured['framework']);
        $this->assertSame('layouts.shell', $captured['layout']);
        $this->assertSame('react', $captured['route_framework']);
        $this->assertSame('layouts.shell', $captured['route_layout']);
    }

    public function testAdminProfileAndSettingsGeneratorsCreateFiles(): void
    {
        $scaffolder = new Scaffolder();

        $admin = $scaffolder->generateAdminView('testfw');
        $profile = $scaffolder->generateProfileView('testfw');
        $settings = $scaffolder->generateSettingsView('testfw');
        $adminComponent = $scaffolder->generateAdminComponent('testfw');
        $profileComponent = $scaffolder->generateProfileComponent('testfw');
        $settingsComponent = $scaffolder->generateSettingsComponent('testfw');
        $layout = $scaffolder->generateLayout('testfw', 'shell');

        $this->assertTrue(file_exists($admin));
        $this->assertTrue(file_exists($profile));
        $this->assertTrue(file_exists($settings));
        $this->assertTrue(file_exists($layout));
        $this->assertCount(2, $adminComponent);
        $this->assertCount(2, $profileComponent);
        $this->assertCount(2, $settingsComponent);

        foreach ([$admin, $profile, $settings, $layout, ...$adminComponent, ...$profileComponent, ...$settingsComponent] as $path) {
            @unlink($path);
        }

        @rmdir(dirname($adminComponent[0]));
        @rmdir(dirname($adminComponent[1]));
        @rmdir(dirname($profileComponent[0]));
        @rmdir(dirname($profileComponent[1]));
        @rmdir(dirname($settingsComponent[0]));
        @rmdir(dirname($settingsComponent[1]));
        @rmdir(dirname($admin));
        @rmdir(dirname(dirname($admin)));
        @rmdir(dirname($profile));
        @rmdir(dirname($settings));
        @rmdir(dirname($layout));
        @rmdir(dirname(dirname($layout)));
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
