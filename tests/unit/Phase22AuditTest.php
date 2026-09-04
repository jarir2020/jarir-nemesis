<?php
declare(strict_types=1);

// Nemesis 7.1.2 — regression coverage for the improvement-plan audit.

use Nemesis\Contracts\MiddlewareInterface;
use Nemesis\Core\Cache;
use Nemesis\Core\Cache\ArrayCacheDriver;
use Nemesis\Http\Middleware\CorsMiddleware;
use Nemesis\Http\Pipeline;
use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Support\URL;
use Nemesis\Testing\TestCase;

class Phase22TerminatingMiddleware implements MiddlewareInterface
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

class Phase22AuditTest extends TestCase
{
    private array $server = [];
    private array $get = [];
    private array $post = [];
    private string|false $appKey = false;

    public function setUp(): void
    {
        $this->server = $_SERVER;
        $this->get = $_GET;
        $this->post = $_POST;
        $this->appKey = getenv('APP_KEY');
        Cache::setDriver(new ArrayCacheDriver());
    }

    public function tearDown(): void
    {
        $_SERVER = $this->server;
        $_GET = $this->get;
        $_POST = $this->post;

        $ref = new \ReflectionClass(Cache::class);
        $driver = $ref->getProperty('driver');
        $driver->setAccessible(true);
        $driver->setValue(null, null);

        if ($this->appKey === false) {
            putenv('APP_KEY');
        } else {
            putenv('APP_KEY=' . $this->appKey);
        }
    }

    public function testRequestExposesServerPathQueryAndHeaders(): void
    {
        $_SERVER['REQUEST_URI'] = '/orders?status=paid';
        $_SERVER['HTTP_X_REQUEST_ID'] = 'audit-22';
        $_GET = [];

        $request = new Request();

        $this->assertSame('/orders', $request->path());
        $this->assertSame('paid', $request->query('status'));
        $this->assertSame('audit-22', $request->headers('X-Request-Id'));
        $this->assertSame('/orders?status=paid', $request->server('REQUEST_URI'));
    }

    public function testCacheFacadeSupportsArrayDriverAndRemember(): void
    {
        $calls = 0;
        $first = Cache::remember('audit-key', 60, function () use (&$calls): string {
            $calls++;
            return 'cached-value';
        });
        $second = Cache::remember('audit-key', 60, function () use (&$calls): string {
            $calls++;
            return 'recomputed';
        });

        $this->assertSame('cached-value', $first);
        $this->assertSame('cached-value', $second);
        $this->assertSame(1, $calls);
        $this->assertTrue(Cache::has('audit-key'));
    }

    public function testCorsMiddlewareReturnsResponseHeaders(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_ORIGIN'] = 'https://app.test';

        $response = (new CorsMiddleware(['origins' => ['https://app.test']]))
            ->handle(new Request(), fn(): Response => Response::text('ok'));

        $this->assertSame('https://app.test', $response->getHeaders()['Access-Control-Allow-Origin']);
        $this->assertSame('Origin', $response->getHeaders()['Vary']);
    }

    public function testSignedUrlPreservesExistingQueryString(): void
    {
        putenv('APP_KEY=' . str_repeat('a', 64));
        $signed = URL::sign('https://app.test/download?file=report.pdf', 60);

        $this->assertTrue(URL::verifySign($signed));
        $this->assertStringContainsString('file=report.pdf', $signed);
        $this->assertFalse(URL::verifySign(str_replace('report.pdf', 'other.pdf', $signed)));
    }

    public function testPipelineRunsTerminableMiddleware(): void
    {
        Phase22TerminatingMiddleware::$terminated = false;
        $request = new Request();
        $pipeline = (new Pipeline())->send($request)->through([
            new Phase22TerminatingMiddleware(),
        ]);
        $response = $pipeline->then(fn(): Response => Response::text('ok'));

        $pipeline->terminate($request, $response);

        $this->assertTrue(Phase22TerminatingMiddleware::$terminated);
    }
}
