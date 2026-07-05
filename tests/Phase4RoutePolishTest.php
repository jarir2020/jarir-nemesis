<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Router\Router;
use Nemesis\Testing\TestCase;

class Phase4RoutePolishController
{
    public function dashboard(): string
    {
        return 'dashboard';
    }
}

class Phase4RoutePolishTest extends TestCase
{
    private string $routeCacheFile;
    private ?string $routeCacheBackup = null;
    private string $phpExportFile;
    private string $yamlExportFile;

    public function setUp(): void
    {
        $this->phpExportFile = sys_get_temp_dir() . '/nemesis-route-export-' . uniqid('', true) . '.php';
        $this->yamlExportFile = sys_get_temp_dir() . '/nemesis-route-export-' . uniqid('', true) . '.yaml';

        foreach ([$this->phpExportFile, $this->yamlExportFile] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
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
        foreach ([$this->phpExportFile, $this->yamlExportFile] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        if ($this->routeCacheBackup !== null) {
            if (!is_dir(dirname($this->routeCacheFile))) {
                mkdir(dirname($this->routeCacheFile), 0755, true);
            }
            file_put_contents($this->routeCacheFile, $this->routeCacheBackup);
        }
    }

    public function testRouteFiltersAndMatchingDiagnostics(): void
    {
        $router = new Router();
        Router::setInstance($router);

        $router->frontendGroup('react', 'app', function (Router $router): void {
            $router->get('/dashboard', [Phase4RoutePolishController::class, 'dashboard'])->name('dashboard.index');
        });

        $router->group([
            'middleware' => ['web', 'auth'],
            'framework' => 'vue',
            'layout' => 'panel',
        ], function (Router $router): void {
            $router->post('/settings', fn () => 'settings')->name('settings.update');
        });

        $router->fallback(fn () => 'fallback');

        $methodFiltered = $router->routeSummary(['method' => 'GET']);
        $this->assertCount(1, $methodFiltered);
        $this->assertSame('GET', $methodFiltered[0]['method']);

        $frameworkFiltered = $router->routeSummary(['framework' => 'vue']);
        $this->assertCount(1, $frameworkFiltered);
        $this->assertSame('vue', $frameworkFiltered[0]['framework']);

        $middlewareFiltered = $router->routeSummary(['middleware' => 'web']);
        $this->assertCount(1, $middlewareFiltered);
        $this->assertContains('web', $middlewareFiltered[0]['middleware']);

        $match = $router->matchDiagnostics('/dashboard', 'GET', 'localhost');
        $this->assertTrue($match['matched']);
        $this->assertSame('/dashboard', $match['matched_route']['uri']);
        $this->assertSame('react', $match['matched_route']['framework']);

        $missing = $router->matchDiagnostics('/missing', 'GET', 'localhost');
        $this->assertFalse($missing['matched']);
        $this->assertNotNull($missing['fallback_route']);
        $this->assertGreaterThanOrEqual(2, count($missing['checked_routes']));
        $this->assertContains('uri mismatch', $missing['checked_routes'][0]['reasons']);
    }

    public function testRouteExportsPhpAndYamlFormats(): void
    {
        $router = new Router();
        Router::setInstance($router);

        $router->get('/alpha', fn () => 'alpha')->name('alpha.index');
        $router->group(['framework' => 'react', 'layout' => 'app'], function (Router $router): void {
            $router->get('/beta', [Phase4RoutePolishController::class, 'dashboard'])->name('beta.index');
        });

        $phpPath = $router->exportRoutes($this->phpExportFile, 'php');
        $this->assertSame($this->phpExportFile, $phpPath);
        $this->assertTrue(file_exists($phpPath));

        $payload = include $phpPath;
        $this->assertIsArray($payload);
        $this->assertSame(2, $payload['diagnostics']['route_count']);
        $this->assertSame('react', $payload['routes'][1]['framework']);

        $yamlPath = $router->exportRoutes($this->yamlExportFile, 'yaml', ['framework' => 'react']);
        $this->assertSame($this->yamlExportFile, $yamlPath);
        $yaml = file_get_contents($yamlPath);
        $this->assertStringContainsString('diagnostics:', (string) $yaml);
        $this->assertStringContainsString('routes:', (string) $yaml);
        $this->assertStringContainsString('framework: react', (string) $yaml);
    }

    public function testRouteListCommandSupportsFilteringAndExportFormat(): void
    {
        $listCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' route:list --method=GET --framework=react --middleware=web 2>&1';
        $listOutput = [];
        $listCode = 0;
        exec($listCmd, $listOutput, $listCode);

        $listText = implode("\n", $listOutput);
        $this->assertSame(0, $listCode);
        $this->assertStringContainsString('Route diagnostics', $listText);
        $this->assertStringContainsString('Filters:', $listText);
        $this->assertStringContainsString('Method', $listText);

        $exportCmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' route:export --format=php ' . escapeshellarg($this->phpExportFile) . ' 2>&1';
        $exportOutput = [];
        $exportCode = 0;
        exec($exportCmd, $exportOutput, $exportCode);

        $this->assertSame(0, $exportCode);
        $this->assertTrue(file_exists($this->phpExportFile));
        $this->assertStringContainsString('Routes exported to', implode("\n", $exportOutput));
        $this->assertIsArray(include $this->phpExportFile);
    }

    public function testRouteDiagnoseCommandPrintsMatchReport(): void
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' route:diagnose /_health GET 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        $text = implode("\n", $output);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Route diagnostics', $text);
        $this->assertStringContainsString('Requested:', $text);
        $this->assertStringContainsString('/_health', $text);
        $this->assertStringContainsString('Matched: yes', $text);
        $this->assertStringContainsString('Framework', $text);
    }

    public function testRouteDiagnoseJsonModePrintsMachineReadableReport(): void
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' route:diagnose /_health GET --json 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        $text = implode("\n", $output);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('"requested"', $text);
        $this->assertStringContainsString('"matched"', $text);
        $payload = json_decode($text, true);
        $this->assertIsArray($payload);
        $this->assertTrue($payload['matched']);
        $this->assertSame('/_health', $payload['requested']['uri']);
        $this->assertStringContainsString("\n    \"requested\"", $text);
    }

    public function testRouteDiagnoseBriefJsonModePrintsCompactPayload(): void
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' route:diagnose /_health GET --json --brief 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        $text = implode("\n", $output);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('"requested":{"uri":"/_health"', $text);
        $this->assertStringNotContainsString("\n    \"requested\"", $text);
    }
}

$test = new Phase4RoutePolishTest();

echo "--- Phase 4 Route Polish Test ---\n";

foreach ([
    'testRouteFiltersAndMatchingDiagnostics',
    'testRouteExportsPhpAndYamlFormats',
    'testRouteListCommandSupportsFilteringAndExportFormat',
    'testRouteDiagnoseCommandPrintsMatchReport',
    'testRouteDiagnoseJsonModePrintsMachineReadableReport',
    'testRouteDiagnoseBriefJsonModePrintsCompactPayload',
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

echo "\n--- Phase 4 Route Polish Test Complete ---\n";
