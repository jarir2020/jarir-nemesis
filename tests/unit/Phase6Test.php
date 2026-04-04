<?php
declare(strict_types=1);

// Nemesis 4.0.0 — Phase 6 Test Suite | Created: 2026-04-02
// Tests: PSR-11 Container (has, get, make, bind, singleton, auto-wiring)

use Nemesis\Testing\TestCase;
use Nemesis\Core\Container;
use Nemesis\Contracts\ContainerInterface;

// ---------------------------------------------------------------------------
// Stub classes used in tests (no autoload needed — defined inline)
// ---------------------------------------------------------------------------

class Phase6_SimpleService {}

class Phase6_WithDefault
{
    public string $name;
    public function __construct(string $name = 'default') {
        $this->name = $name;
    }
}

class Phase6_WithDep
{
    public Phase6_SimpleService $svc;
    public function __construct(Phase6_SimpleService $svc) {
        $this->svc = $svc;
    }
}

// ---------------------------------------------------------------------------

class Phase6Test extends TestCase
{
    private Container $c;

    public function setUp(): void
    {
        // Fresh container per test — avoids singleton leakage between tests
        $this->c = new Container;
    }

    // -------------------------------------------------------------------------
    // Contract compliance
    // -------------------------------------------------------------------------

    public function testContainerImplementsContainerInterface(): void
    {
        $this->assertInstanceOf(ContainerInterface::class, $this->c);
    }

    // -------------------------------------------------------------------------
    // has() — PSR-11
    // -------------------------------------------------------------------------

    public function testHasReturnsTrueForBoundAbstract(): void
    {
        $this->c->bind('foo', Phase6_SimpleService::class);
        $this->assertTrue($this->c->has('foo'));
    }

    public function testHasReturnsTrueForCachedSingleton(): void
    {
        $this->c->singleton(Phase6_SimpleService::class, Phase6_SimpleService::class);
        $this->c->make(Phase6_SimpleService::class); // populate instances cache
        $this->assertTrue($this->c->has(Phase6_SimpleService::class));
    }

    public function testHasReturnsTrueForExistingUnboundClass(): void
    {
        // Auto-wiring: class exists even without explicit bind
        $this->assertTrue($this->c->has(Phase6_SimpleService::class));
    }

    public function testHasReturnsFalseForNonExistentClass(): void
    {
        $this->assertFalse($this->c->has('NonExistent\\Totally\\FakeClass'));
    }

    // -------------------------------------------------------------------------
    // get() — PSR-11 (delegates to make)
    // -------------------------------------------------------------------------

    public function testGetResolvesBoundClosure(): void
    {
        $this->c->bind('greeting', fn() => 'hello');
        $this->assertEquals('hello', $this->c->get('greeting'));
    }

    public function testGetAutoWiresUnboundClass(): void
    {
        $obj = $this->c->get(Phase6_SimpleService::class);
        $this->assertInstanceOf(Phase6_SimpleService::class, $obj);
    }

    // -------------------------------------------------------------------------
    // bind() — transient (new instance each call)
    // -------------------------------------------------------------------------

    public function testBindWithClosureReturnsValue(): void
    {
        $this->c->bind('answer', fn() => 42);
        $this->assertSame(42, $this->c->make('answer'));
    }

    public function testBindWithClassStringReturnsInstance(): void
    {
        $this->c->bind('svc', Phase6_SimpleService::class);
        $this->assertInstanceOf(Phase6_SimpleService::class, $this->c->make('svc'));
    }

    public function testBindIsTransient(): void
    {
        $this->c->bind(Phase6_SimpleService::class);
        $a = $this->c->make(Phase6_SimpleService::class);
        $b = $this->c->make(Phase6_SimpleService::class);
        $this->assertFalse($a === $b, 'Transient bind must return different instances');
    }

    // -------------------------------------------------------------------------
    // singleton() — shared instance
    // -------------------------------------------------------------------------

    public function testSingletonReturnsSameInstance(): void
    {
        $this->c->singleton(Phase6_SimpleService::class);
        $a = $this->c->make(Phase6_SimpleService::class);
        $b = $this->c->make(Phase6_SimpleService::class);
        $this->assertSame($a, $b);
    }

    public function testSingletonWithClosureReturnsSameInstance(): void
    {
        $this->c->singleton('counter', fn() => new Phase6_SimpleService);
        $a = $this->c->make('counter');
        $b = $this->c->make('counter');
        $this->assertSame($a, $b);
    }

    // -------------------------------------------------------------------------
    // Auto-wiring (no explicit bind needed)
    // -------------------------------------------------------------------------

    public function testMakeAutoWiresSimpleClass(): void
    {
        $obj = $this->c->make(Phase6_SimpleService::class);
        $this->assertInstanceOf(Phase6_SimpleService::class, $obj);
    }

    public function testMakeAutoWiresClassDependency(): void
    {
        $obj = $this->c->make(Phase6_WithDep::class);
        $this->assertInstanceOf(Phase6_WithDep::class, $obj);
        $this->assertInstanceOf(Phase6_SimpleService::class, $obj->svc);
    }

    public function testMakeUsesDefaultParameterValue(): void
    {
        $obj = $this->c->make(Phase6_WithDefault::class);
        $this->assertEquals('default', $obj->name);
    }

    // -------------------------------------------------------------------------
    // Primitive overrides via make($abstract, $parameters)
    // -------------------------------------------------------------------------

    public function testMakeWithPrimitiveOverride(): void
    {
        $obj = $this->c->make(Phase6_WithDefault::class, ['name' => 'nemesis']);
        $this->assertEquals('nemesis', $obj->name);
    }

    // -------------------------------------------------------------------------
    // getInstance() / setInstance() — global singleton accessor
    // -------------------------------------------------------------------------

    public function testGetInstanceReturnsSameObject(): void
    {
        $a = Container::getInstance();
        $b = Container::getInstance();
        $this->assertSame($a, $b);
    }

    public function testSetInstanceOverridesGlobalSingleton(): void
    {
        $custom = new Container;
        Container::setInstance($custom);
        $this->assertSame($custom, Container::getInstance());

        // Restore to a fresh instance so other tests are unaffected
        Container::setInstance(new Container);
    }

    // -------------------------------------------------------------------------
    // Interface binding — abstract → concrete
    // -------------------------------------------------------------------------

    public function testInterfaceBindingResolvesToConcrete(): void
    {
        $this->c->bind(ContainerInterface::class, Container::class);
        $obj = $this->c->make(ContainerInterface::class);
        $this->assertInstanceOf(Container::class, $obj);
    }
}
