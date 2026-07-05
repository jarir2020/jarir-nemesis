<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Router\Router;
use Nemesis\Testing\TestCase;

class Phase4RouteDiagnosticsController
{
    public function index(): string
    {
        return 'ok';
    }
}

class Phase4RouteDiagnosticsTest extends TestCase
{
    private string $exportFile;
    private string $routeCacheFile;
    private ?string $routeCacheBackup = null;

    public function setUp(): void
    {
        $this->exportFile = sys_get_temp_dir() . '/nemesis-route-export-' . uniqid('', true) . '.json';
        if (file_exists($this->exportFile)) {
            unlink($this->exportFile);
        }

        $ref = new ReflectionClass(Router::class);
        $prop = $ref->getProperty('cachedPath');
        $prop->setAccessible(true);
        $this->routeCacheFile = $prop->getValue();

        if (file_exists($this->routeCacheFile)) {
            $this->routeCacheBackup = file_get_contents($this->routeCacheFile);
            unlink($this->routeCacheFile);
        }
    }

    public function tearDown(): void
    {
        if (file_exists($this->exportFile)) {
            unlink($this->exportFile);
        }

        if ($this->routeCacheBackup !== null) {
            if (!is_dir(dirname($this->routeCacheFile))) {
                mkdir(dirname($this->routeCacheFile), 0755, true);
            }
            file_put_contents($this->routeCacheFile, $this->routeCacheBackup);
        }
    }

    public function testRouterDiagnosticsAndExportPayload(): void
    {
        $router = new Router();
        Router::setInstance($router);

        $router->get('/alpha', [Phase4RouteDiagnosticsController::class, 'index'])->name('alpha.index');
        $router->post('/beta', fn() => 'beta')->name('beta.store');
        $router->fallback(fn() => 'fallback');

        $summary = $router->routeSummary();
        $this->assertCount(3, $summary);
        $this->assertSame('GET', $summary[0]['method']);
        $this->assertSame('/alpha', $summary[0]['uri']);
        $this->assertTrue($summary[0]['cacheable']);
        $this->assertFalse($summary[1]['cacheable']);
        $this->assertSame('Closure', $summary[1]['action']);

        $diagnostics = $router->diagnostics();
        $this->assertSame(3, $diagnostics['route_count']);
        $this->assertSame(2, $diagnostics['named_route_count']);
        $this->assertTrue($diagnostics['fallback_route']);
        $this->assertSame(1, $diagnostics['methods']['GET']);
        $this->assertSame(1, $diagnostics['methods']['POST']);
        $this->assertSame(1, $diagnostics['methods']['ANY']);

        $path = $router->exportRoutes($this->exportFile);
        $this->assertSame($this->exportFile, $path);
        $this->assertTrue(file_exists($path));

        $payload = json_decode(file_get_contents($path), true);
        $this->assertSame(3, $payload['diagnostics']['route_count']);
        $this->assertCount(3, $payload['routes']);
    }

    public function testRouteListCommandOutputsDiagnostics(): void
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' route:list 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        $text = implode("\n", $output);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Route diagnostics', $text);
        $this->assertStringContainsString('Method', $text);
    }
}

$test = new Phase4RouteDiagnosticsTest();

echo "--- Phase 4 Route Diagnostics Test ---\n";

foreach ([
    'testRouterDiagnosticsAndExportPayload',
    'testRouteListCommandOutputsDiagnostics',
] as $method) {
    echo "Running {$method}... ";
    try {
        $test->setUp();
        $test->{$method}();
        $test->tearDown();
        echo "PASS\n";
    } catch (\Throwable $e) {
        echo "FAIL: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n--- Phase 4 Route Diagnostics Test Complete ---\n";
