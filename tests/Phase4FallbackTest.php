<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Http\Response;
use Nemesis\Router\Router;
use Nemesis\Testing\TestCase;

class Phase4FallbackTest extends TestCase
{
    public function testRegisteredFallbackRouteHandlesMissingPaths(): void
    {
        $router = new Router();
        Router::setInstance($router);

        $router->fallback(function () {
            return Response::make('fallback hit', 404);
        });

        $response = $router->dispatch('/missing-page', 'GET');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(404, $response->getStatus());
        $this->assertSame('fallback hit', $response->getContent());
    }

    public function testMissingRouteReturns404Response(): void
    {
        $router = new Router();
        Router::setInstance($router);

        $response = $router->dispatch('/still-missing', 'GET');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(404, $response->getStatus());
        $this->assertStringContainsString('404', $response->getContent());
    }
}

$test = new Phase4FallbackTest();

echo "--- Phase 4 Fallback Test ---\n";

foreach ([
    'testRegisteredFallbackRouteHandlesMissingPaths',
    'testMissingRouteReturns404Response',
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

echo "\n--- Phase 4 Fallback Test Complete ---\n";
