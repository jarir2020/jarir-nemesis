<?php
declare(strict_types=1);

// Nemesis 4.0.0 — Phase 3 Test Suite | Created: 2026-04-02
// Tests: Response, Request, Pipeline, all 9 middleware (MiddlewareInterface),
//        Kernel groups & aliases

use Nemesis\Testing\TestCase;
use Nemesis\Http\Response;
use Nemesis\Http\Request;
use Nemesis\Http\Pipeline;
use Nemesis\Contracts\MiddlewareInterface;

// ---------------------------------------------------------------------------
// Stub middleware used in Pipeline tests
// ---------------------------------------------------------------------------

class Phase3_PassthroughMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        return $next($request);
    }
}

class Phase3_ShortCircuitMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        return Response::json(['blocked' => true], 403);
    }
}

class Phase3_AttributeMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $request = $request->withAttribute('user_id', 42);
        return $next($request);
    }
}

class Phase3_TerminableMiddleware implements MiddlewareInterface
{
    public static bool $terminated = false;

    public function handle(Request $request, callable $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        self::$terminated = true;
    }
}

// ---------------------------------------------------------------------------

class Phase3Test extends TestCase
{
    // =========================================================================
    // Response
    // =========================================================================

    public function testResponseMakeReturnsInstance(): void
    {
        $r = Response::make('hello', 200);
        $this->assertInstanceOf(Response::class, $r);
    }

    public function testResponseGetContent(): void
    {
        $r = Response::make('hello');
        $this->assertEquals('hello', $r->getContent());
    }

    public function testResponseGetStatus(): void
    {
        $r = Response::make('', 201);
        $this->assertEquals(201, $r->getStatus());
    }

    public function testResponseJsonSetsContentType(): void
    {
        $r = Response::json(['key' => 'val']);
        $headers = $r->getHeaders();
        $this->assertEquals('application/json', $headers['Content-Type']);
    }

    public function testResponseJsonEncodesData(): void
    {
        $r = Response::json(['x' => 1]);
        $this->assertEquals("{\n    \"x\": 1\n}", $r->getContent());
    }

    public function testResponseJsonCanBeBriefed(): void
    {
        $r = Response::json(['x' => 1], 200, [], false);
        $this->assertEquals('{"x":1}', $r->getContent());
    }

    public function testResponseTextSetsContentType(): void
    {
        $r = Response::text('plain');
        $headers = $r->getHeaders();
        $this->assertStringContainsString('text/plain', $headers['Content-Type']);
    }

    public function testResponseRedirectIsRedirect(): void
    {
        $r = Response::redirect('/login');
        $this->assertTrue($r->isRedirect());
    }

    public function testResponseRedirectDefaultStatus302(): void
    {
        $r = Response::redirect('/login');
        $this->assertEquals(302, $r->getStatus());
    }

    public function testResponseRedirectCustomStatus(): void
    {
        $r = Response::redirect('/home', 301);
        $this->assertEquals(301, $r->getStatus());
    }

    public function testResponseWithStatusClonesObject(): void
    {
        $original = Response::make('body', 200);
        $modified = $original->withStatus(404);
        $this->assertEquals(200, $original->getStatus());
        $this->assertEquals(404, $modified->getStatus());
    }

    public function testResponseWithHeaderClonesObject(): void
    {
        $original = Response::make('body');
        $modified = $original->withHeader('X-Custom', 'yes');
        $this->assertFalse(isset($original->getHeaders()['X-Custom']));
        $this->assertEquals('yes', $modified->getHeaders()['X-Custom']);
    }

    public function testResponseWithContentClonesObject(): void
    {
        $original = Response::make('old');
        $modified = $original->withContent('new');
        $this->assertEquals('old', $original->getContent());
        $this->assertEquals('new', $modified->getContent());
    }

    public function testResponseDownloadMethodExists(): void
    {
        $this->assertTrue(method_exists(Response::class, 'download'));
    }

    public function testResponseStreamMethodExists(): void
    {
        $this->assertTrue(method_exists(Response::class, 'stream'));
    }

    public function testResponseViewMethodExists(): void
    {
        $this->assertTrue(method_exists(Response::class, 'view'));
    }

    public function testResponseStreamStoresCallback(): void
    {
        $cb = fn() => null;
        $r  = Response::stream($cb, 200);
        $this->assertInstanceOf(Response::class, $r);
        $this->assertEquals(200, $r->getStatus());
    }

    public function testResponseDownloadReturnsInstance(): void
    {
        $r = Response::download('/tmp/fake.pdf', 'report.pdf');
        $this->assertInstanceOf(Response::class, $r);
    }

    // =========================================================================
    // Request — withAttribute / getAttribute
    // =========================================================================

    public function testRequestWithAttributeReturnsNewInstance(): void
    {
        $req  = new Request();
        $req2 = $req->withAttribute('user', 'alice');
        $this->assertFalse($req === $req2);
    }

    public function testRequestGetAttributeReadsValue(): void
    {
        $req  = new Request();
        $req2 = $req->withAttribute('role', 'admin');
        $this->assertEquals('admin', $req2->getAttribute('role'));
    }

    public function testRequestGetAttributeDefaultWhenMissing(): void
    {
        $req = new Request();
        $this->assertNull($req->getAttribute('nope'));
        $this->assertEquals('default', $req->getAttribute('nope', 'default'));
    }

    public function testRequestGetAttributesReturnsAll(): void
    {
        $req = (new Request())->withAttribute('a', 1)->withAttribute('b', 2);
        $this->assertCount(2, $req->getAttributes());
    }

    public function testRequestOriginalUnchangedAfterWithAttribute(): void
    {
        $original = new Request();
        $original->withAttribute('x', 99);
        $this->assertNull($original->getAttribute('x'));
    }

    public function testRequestValidateMethodExists(): void
    {
        $this->assertTrue(method_exists(Request::class, 'validate'));
    }

    // =========================================================================
    // Pipeline
    // =========================================================================

    public function testPipelineReturnsResponseFromDestination(): void
    {
        $req  = new Request();
        $pipe = new Pipeline();

        $response = $pipe->send($req)
            ->through([])
            ->then(fn($r) => Response::make('ok'));

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('ok', $response->getContent());
    }

    public function testPipelineWrapsStringInResponse(): void
    {
        $req      = new Request();
        $response = (new Pipeline())
            ->send($req)
            ->through([])
            ->then(fn($r) => 'plain string');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('plain string', $response->getContent());
    }

    public function testPipelineWrapsNullInEmptyResponse(): void
    {
        $req      = new Request();
        $response = (new Pipeline())
            ->send($req)
            ->through([])
            ->then(fn($r) => null);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testPipelinePassthroughMiddleware(): void
    {
        $req      = new Request();
        $response = (new Pipeline())
            ->send($req)
            ->through([new Phase3_PassthroughMiddleware()])
            ->then(fn($r) => Response::make('reached'));

        $this->assertEquals('reached', $response->getContent());
    }

    public function testPipelineShortCircuit(): void
    {
        $req      = new Request();
        $response = (new Pipeline())
            ->send($req)
            ->through([new Phase3_ShortCircuitMiddleware()])
            ->then(fn($r) => Response::make('should not reach'));

        $this->assertEquals(403, $response->getStatus());
    }

    public function testPipelineTerminate(): void
    {
        Phase3_TerminableMiddleware::$terminated = false;
        $req  = new Request();
        $pipe = new Pipeline();

        $response = $pipe->send($req)
            ->through([new Phase3_TerminableMiddleware()])
            ->then(fn($r) => Response::make('done'));

        $pipe->terminate($req, $response);
        $this->assertTrue(Phase3_TerminableMiddleware::$terminated);
    }

    public function testPipelineAttributeMiddlewarePassesData(): void
    {
        $req  = new Request();
        $captured = null;

        (new Pipeline())
            ->send($req)
            ->through([new Phase3_AttributeMiddleware()])
            ->then(function ($r) use (&$captured) {
                $captured = $r->getAttribute('user_id');
                return Response::make('');
            });

        $this->assertEquals(42, $captured);
    }

    // =========================================================================
    // MiddlewareInterface compliance — all 9 middleware
    // =========================================================================

    public function testCheckForMaintenanceModeImplementsInterface(): void
    {
        $this->assertInstanceOf(MiddlewareInterface::class, new \Nemesis\Http\Middleware\CheckForMaintenanceMode());
    }

    public function testStartSessionImplementsInterface(): void
    {
        $this->assertInstanceOf(MiddlewareInterface::class, new \Nemesis\Http\Middleware\StartSession());
    }

    public function testVerifyCsrfTokenImplementsInterface(): void
    {
        $this->assertInstanceOf(MiddlewareInterface::class, new \Nemesis\Http\Middleware\VerifyCsrfToken());
    }

    public function testThrottleRequestsImplementsInterface(): void
    {
        $this->assertInstanceOf(MiddlewareInterface::class, new \Nemesis\Http\Middleware\ThrottleRequests());
    }

    public function testTestMiddlewareImplementsInterface(): void
    {
        $this->assertInstanceOf(MiddlewareInterface::class, new \Nemesis\Http\Middleware\TestMiddleware());
    }

    public function testCorsMiddlewareImplementsInterface(): void
    {
        $this->assertInstanceOf(MiddlewareInterface::class, new \Nemesis\Http\Middleware\CorsMiddleware());
    }

    public function testSecurityHeadersMiddlewareImplementsInterface(): void
    {
        $this->assertInstanceOf(MiddlewareInterface::class, new \Nemesis\Http\Middleware\SecurityHeadersMiddleware());
    }

    public function testApiVersionMiddlewareImplementsInterface(): void
    {
        $this->assertInstanceOf(MiddlewareInterface::class, new \Nemesis\Http\Middleware\ApiVersionMiddleware());
    }

    public function testDebugBarMiddlewareImplementsInterface(): void
    {
        $this->assertInstanceOf(MiddlewareInterface::class, new \Nemesis\Http\Middleware\DebugBarMiddleware());
    }

    // =========================================================================
    // Kernel — groups & aliases
    // =========================================================================

    public function testKernelHasWebGroup(): void
    {
        $kernel = new \App\Http\Kernel();
        $groups = $kernel->getMiddlewareGroups();
        $this->assertTrue(isset($groups['web']));
    }

    public function testKernelHasApiGroup(): void
    {
        $kernel = new \App\Http\Kernel();
        $groups = $kernel->getMiddlewareGroups();
        $this->assertTrue(isset($groups['api']));
    }

    public function testKernelHasThrottleAlias(): void
    {
        $kernel = new \App\Http\Kernel();
        $this->assertTrue(isset($kernel->getRouteMiddleware()['throttle']));
    }

    public function testKernelHasCorsAlias(): void
    {
        $kernel = new \App\Http\Kernel();
        $this->assertTrue(isset($kernel->getRouteMiddleware()['cors']));
    }

    public function testKernelHasSecurityAlias(): void
    {
        $kernel = new \App\Http\Kernel();
        $this->assertTrue(isset($kernel->getRouteMiddleware()['security']));
    }

    public function testKernelHasApiVersionAlias(): void
    {
        $kernel = new \App\Http\Kernel();
        $this->assertTrue(isset($kernel->getRouteMiddleware()['api.version']));
    }

    public function testKernelHasDebugbarAlias(): void
    {
        $kernel = new \App\Http\Kernel();
        $this->assertTrue(isset($kernel->getRouteMiddleware()['debugbar']));
    }

    public function testStartSessionHasTerminateMethod(): void
    {
        $m = new \Nemesis\Http\Middleware\StartSession();
        $this->assertTrue(method_exists($m, 'terminate'));
    }
}
