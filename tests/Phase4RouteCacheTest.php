<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Router\Router;
use Nemesis\Testing\TestCase;

class Phase4RouteCacheController
{
    public function index(): string
    {
        return 'cache';
    }
}

class Phase4RouteCacheTest extends TestCase
{
    private string $cachePath;
    private ?string $backup = null;

    public function setUp(): void
    {
        $ref = new ReflectionClass(Router::class);
        $prop = $ref->getProperty('cachedPath');
        $prop->setAccessible(true);
        $this->cachePath = $prop->getValue();

        if (file_exists($this->cachePath)) {
            $this->backup = file_get_contents($this->cachePath);
            unlink($this->cachePath);
        }
    }

    public function tearDown(): void
    {
        if ($this->backup !== null) {
            if (!is_dir(dirname($this->cachePath))) {
                mkdir(dirname($this->cachePath), 0755, true);
            }
            file_put_contents($this->cachePath, $this->backup);
        } elseif (file_exists($this->cachePath)) {
            unlink($this->cachePath);
        }
    }

    public function testWarmCacheAndClearCacheHelpers(): void
    {
        $router = new Router();
        $router->get('/cache-check', [Phase4RouteCacheController::class, 'index'])->name('cache.check');

        $path = $router->warmCache();
        $this->assertSame($this->cachePath, $path);
        $this->assertTrue(file_exists($path));

        $cached = require $path;
        $this->assertNotEmpty($cached);
        $this->assertSame('/cache-check', $cached[array_key_last($cached)]['uri']);

        $this->assertTrue(Router::clearCache());
        $this->assertFalse(file_exists($path));
    }

    public function testRouteCacheCommandPathWorks(): void
    {
        $this->assertSame(0, $this->runCommand('route:cache'));
        $this->assertTrue(file_exists($this->cachePath));

        $this->assertSame(0, $this->runCommand('route:clear'));
        $this->assertFalse(file_exists($this->cachePath));
    }

    private function runCommand(string $command): int
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(base_path('bin/nemesis')) . ' ' . $command . ' 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);
        return $code;
    }
}

$test = new Phase4RouteCacheTest();

echo "--- Phase 4 Route Cache Test ---\n";

foreach ([
    'testWarmCacheAndClearCacheHelpers',
    'testRouteCacheCommandPathWorks',
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

echo "\n--- Phase 4 Route Cache Test Complete ---\n";
