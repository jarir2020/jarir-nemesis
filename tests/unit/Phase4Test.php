<?php
declare(strict_types=1);

// Nemesis 4.0.0 — Phase 4 Test Suite | Created: 2026-04-02
// Tests: Router enhancements — named routes, model binding, attribute routing,
//        subdomain groups, fallback, HealthCheck, RouteModelBinder

use Nemesis\Testing\TestCase;
use Nemesis\Router\Router;
use Nemesis\Router\RouteModelBinder;
use Nemesis\Attributes\Route as RouteAttr;

// ---------------------------------------------------------------------------
// Stub controller used for attribute routing tests
// ---------------------------------------------------------------------------

class Phase4_ProductController
{
    #[RouteAttr('GET', '/p4/products', name: 'p4.products.index')]
    public function index(): string { return 'index'; }

    #[RouteAttr('GET', '/p4/products/{id}', name: 'p4.products.show')]
    public function show(): string { return 'show'; }

    #[RouteAttr('POST', '/p4/products', name: 'p4.products.store', middleware: ['auth'])]
    public function store(): string { return 'store'; }
}

// ---------------------------------------------------------------------------

class Phase4Test extends TestCase
{
    private Router $r;

    public function setUp(): void
    {
        // Fresh router — no cache, no global state leakage
        $this->r = new Router();
        RouteModelBinder::flush();
    }

    // -------------------------------------------------------------------------
    // Router class exists and is instantiable
    // -------------------------------------------------------------------------

    public function testRouterClassExists(): void
    {
        $this->assertTrue(class_exists(Router::class));
    }

    public function testRouteModelBinderClassExists(): void
    {
        $this->assertTrue(class_exists(RouteModelBinder::class));
    }

    public function testRouteAttributeClassExists(): void
    {
        $this->assertTrue(class_exists(\Nemesis\Attributes\Route::class));
    }

    public function testHealthCheckClassExists(): void
    {
        $this->assertTrue(class_exists(\Nemesis\Http\HealthCheck::class));
    }

    // -------------------------------------------------------------------------
    // getInstance() / setInstance()
    // -------------------------------------------------------------------------

    public function testGetInstanceReturnsSameObject(): void
    {
        $a = Router::getInstance();
        $b = Router::getInstance();
        $this->assertSame($a, $b);
    }

    public function testSetInstanceOverridesGlobal(): void
    {
        $custom = new Router();
        Router::setInstance($custom);
        $this->assertSame($custom, Router::getInstance());
        // Restore
        Router::setInstance(new Router());
    }

    // -------------------------------------------------------------------------
    // Named routes — generate()
    // -------------------------------------------------------------------------

    public function testGenerateSimpleNamedRoute(): void
    {
        $this->r->get('/users', fn() => null)->name('users.index');
        $this->assertEquals('/users', $this->r->generate('users.index'));
    }

    public function testGenerateNamedRouteWithParam(): void
    {
        $this->r->get('/users/{id}', fn() => null)->name('users.show');
        $this->assertEquals('/users/42', $this->r->generate('users.show', ['id' => 42]));
    }

    public function testGenerateNamedRouteWithMultipleParams(): void
    {
        $this->r->get('/posts/{post}/comments/{comment}', fn() => null)->name('comments.show');
        $url = $this->r->generate('comments.show', ['post' => 5, 'comment' => 99]);
        $this->assertEquals('/posts/5/comments/99', $url);
    }

    public function testGenerateThrowsForUnknownRoute(): void
    {
        try {
            $this->r->generate('non.existent');
            $this->assertTrue(false, 'Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true);
        }
    }

    public function testGenerateOptionalParamOmitted(): void
    {
        $this->r->get('/archive/{year?}', fn() => null)->name('archive');
        $url = $this->r->generate('archive');
        // No value supplied — optional segment should be stripped
        $this->assertFalse(str_contains($url, '{year'), 'Unresolved placeholder should be removed');
    }

    // -------------------------------------------------------------------------
    // Route registration
    // -------------------------------------------------------------------------

    public function testAddRouteStoresInRoutes(): void
    {
        $this->r->get('/hello', fn() => 'hi')->name('hello');
        $routes = $this->r->getRoutes();
        $found  = array_filter($routes, fn($r) => $r['name'] === 'hello');
        $this->assertFalse(empty($found));
    }

    public function testGroupPrefixApplied(): void
    {
        $this->r->group(['prefix' => 'api/v1'], function (Router $r) {
            $r->get('/ping', fn() => null)->name('api.ping');
        });
        $url = $this->r->generate('api.ping');
        $this->assertEquals('/api/v1/ping', $url);
    }

    public function testNestedGroupPrefixes(): void
    {
        $this->r->group(['prefix' => 'api'], function (Router $r) {
            $r->group(['prefix' => 'v2'], function (Router $r) {
                $r->get('/status', fn() => null)->name('v2.status');
            });
        });
        $this->assertEquals('/api/v2/status', $this->r->generate('v2.status'));
    }

    public function testWhereConstraintStoredOnRoute(): void
    {
        $this->r->get('/items/{id}', fn() => null)->where('id', '[0-9]+')->name('items.show');
        $routes = $this->r->getRoutes();
        $route  = end($routes);
        $this->assertEquals('[0-9]+', $route['constraints']['id']);
    }

    // -------------------------------------------------------------------------
    // Route model binding (Router::bind)
    // -------------------------------------------------------------------------

    public function testRouterBindRegistered(): void
    {
        $this->r->bind('user', fn($id) => ['id' => (int) $id, 'name' => 'Alice']);
        $binders = $this->r->getBinders();
        $this->assertTrue(isset($binders['user']));
    }

    public function testRouterBindResolves(): void
    {
        $this->r->bind('post', fn($id) => ['id' => (int) $id, 'title' => "Post {$id}"]);
        $binders = $this->r->getBinders();
        $result  = ($binders['post'])('7');
        $this->assertEquals(7, $result['id']);
        $this->assertEquals('Post 7', $result['title']);
    }

    // -------------------------------------------------------------------------
    // RouteModelBinder (static registry)
    // -------------------------------------------------------------------------

    public function testRouteModelBinderBind(): void
    {
        RouteModelBinder::bind('tag', fn($id) => ['tag_id' => (int) $id]);
        $this->assertTrue(RouteModelBinder::has('tag'));
    }

    public function testRouteModelBinderResolve(): void
    {
        RouteModelBinder::bind('product', fn($id) => ['product_id' => (int) $id]);
        $result = RouteModelBinder::resolve('product', '99');
        $this->assertEquals(99, $result['product_id']);
    }

    public function testRouteModelBinderResolveUnbound(): void
    {
        // No binder — should return the raw string
        $result = RouteModelBinder::resolve('unknown_param', 'raw-value');
        $this->assertEquals('raw-value', $result);
    }

    public function testRouteModelBinderResolveAll(): void
    {
        RouteModelBinder::bind('cat', fn($id) => "CAT:{$id}");
        $resolved = RouteModelBinder::resolveAll(['cat' => '3', 'other' => 'abc']);
        $this->assertEquals('CAT:3', $resolved['cat']);
        $this->assertEquals('abc', $resolved['other']);
    }

    public function testRouteModelBinderFlush(): void
    {
        RouteModelBinder::bind('temp', fn($id) => $id);
        RouteModelBinder::flush();
        $this->assertFalse(RouteModelBinder::has('temp'));
    }

    public function testRouteModelBinderAll(): void
    {
        RouteModelBinder::bind('foo', fn($id) => $id);
        RouteModelBinder::bind('bar', fn($id) => $id);
        $this->assertCount(2, RouteModelBinder::all());
    }

    // -------------------------------------------------------------------------
    // #[Route] attribute class
    // -------------------------------------------------------------------------

    public function testRouteAttributeIsAttribute(): void
    {
        $ref   = new \ReflectionClass(\Nemesis\Attributes\Route::class);
        $attrs = $ref->getAttributes(\Attribute::class);
        $this->assertTrue(count($attrs) > 0, '#[Route] must be declared as #[Attribute]');
    }

    public function testRouteAttributeIsRepeatable(): void
    {
        $ref    = new \ReflectionClass(\Nemesis\Attributes\Route::class);
        $attrs  = $ref->getAttributes(\Attribute::class);
        $flags  = $attrs[0]->newInstance()->flags;
        $this->assertTrue(($flags & \Attribute::IS_REPEATABLE) !== 0);
    }

    public function testRouteAttributeHoldsValues(): void
    {
        $attr = new \Nemesis\Attributes\Route('POST', '/items', name: 'items.store', middleware: ['auth']);
        $this->assertEquals('POST', $attr->method);
        $this->assertEquals('/items', $attr->uri);
        $this->assertEquals('items.store', $attr->name);
        $this->assertEquals(['auth'], $attr->middleware);
    }

    // -------------------------------------------------------------------------
    // Attribute routing scan — scanAttributes()
    // -------------------------------------------------------------------------

    public function testScanAttributesRegistersRoutes(): void
    {
        $this->r->scanAttributes(Phase4_ProductController::class);
        $routes = $this->r->getRoutes();
        $names  = array_column($routes, 'name');

        $this->assertTrue(in_array('p4.products.index', $names));
        $this->assertTrue(in_array('p4.products.show', $names));
        $this->assertTrue(in_array('p4.products.store', $names));
    }

    public function testScanAttributesGeneratesCorrectUrl(): void
    {
        $this->r->scanAttributes(Phase4_ProductController::class);
        $this->assertEquals('/p4/products', $this->r->generate('p4.products.index'));
        $this->assertEquals('/p4/products/7', $this->r->generate('p4.products.show', ['id' => 7]));
    }

    public function testScanAttributesRegistersMiddleware(): void
    {
        $this->r->scanAttributes(Phase4_ProductController::class);
        $routes = $this->r->getRoutes();
        $store  = current(array_filter($routes, fn($r) => $r['name'] === 'p4.products.store'));
        $this->assertTrue(in_array('auth', $store['middleware']));
    }

    // -------------------------------------------------------------------------
    // Subdomain / domain routing
    // -------------------------------------------------------------------------

    public function testDomainStoredOnRoute(): void
    {
        $this->r->group(['domain' => 'api.example.com'], function (Router $r) {
            $r->get('/ping', fn() => null)->name('subdomain.ping');
        });
        $routes = $this->r->getRoutes();
        $route  = current(array_filter($routes, fn($r) => $r['name'] === 'subdomain.ping'));
        $this->assertEquals('api.example.com', $route['domain']);
    }

    public function testGroupWithoutDomainHasNullDomain(): void
    {
        $this->r->get('/no-domain', fn() => null)->name('no.domain');
        $routes = $this->r->getRoutes();
        $route  = current(array_filter($routes, fn($r) => $r['name'] === 'no.domain'));
        $this->assertNull($route['domain']);
    }

    // -------------------------------------------------------------------------
    // HealthCheck class structure
    // -------------------------------------------------------------------------

    public function testHealthCheckHasHandleMethod(): void
    {
        $ref = new \ReflectionClass(\Nemesis\Http\HealthCheck::class);
        $this->assertTrue($ref->hasMethod('handle'));
    }

    public function testHealthCheckHandleIsPublic(): void
    {
        $ref    = new \ReflectionClass(\Nemesis\Http\HealthCheck::class);
        $method = $ref->getMethod('handle');
        $this->assertTrue($method->isPublic());
    }

    // -------------------------------------------------------------------------
    // _health route registered in routes/route.php
    // -------------------------------------------------------------------------

    public function testHealthRouteExistsInRouteFile(): void
    {
        $content = file_get_contents(base_path('routes/route.php'));
        $this->assertStringContainsString('/_health', $content);
        $this->assertStringContainsString('HealthCheck', $content);
    }

    public function testHealthRouteIsNamed(): void
    {
        $content = file_get_contents(base_path('routes/route.php'));
        $this->assertStringContainsString("'health'", $content);
    }
}
