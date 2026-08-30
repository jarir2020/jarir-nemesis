<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 21 — Multi-tenancy Tests | Created: 2026-04-06

use Nemesis\Testing\TestCase;
use Nemesis\Tenancy\Tenant;
use Nemesis\Tenancy\TenantResolver;
use Nemesis\Tenancy\TenantManager;
use Nemesis\Tenancy\TenantScope;
use Nemesis\Tenancy\TenantAwareCache;
use Nemesis\Core\Database;

// =============================================================================
// Tenant DTO
// =============================================================================

class TenantDtoTest extends TestCase
{
    public function testConstructorAndReadonly(): void
    {
        $t = new Tenant(1, 'Acme Corp', 'acme', 'acme.example.com', 'db_acme', true, ['plan' => 'pro'], '2026-01-01');
        $this->assertEquals(1, $t->id);
        $this->assertEquals('Acme Corp', $t->name);
        $this->assertEquals('acme', $t->slug);
        $this->assertEquals('acme.example.com', $t->domain);
        $this->assertEquals('db_acme', $t->database);
        $this->assertTrue($t->active);
        $this->assertEquals(['plan' => 'pro'], $t->meta);
        $this->assertEquals('2026-01-01', $t->createdAt);
    }

    public function testFromRow(): void
    {
        $row = [
            'id'         => '7',
            'name'       => 'Beta Ltd',
            'slug'       => 'beta',
            'domain'     => 'beta.com',
            'database'   => '',
            'active'     => '1',
            'meta'       => '{"plan":"free"}',
            'created_at' => '2026-02-14',
        ];
        $t = Tenant::fromRow($row);
        $this->assertEquals(7, $t->id);
        $this->assertEquals('Beta Ltd', $t->name);
        $this->assertEquals('beta', $t->slug);
        $this->assertTrue($t->active);
        $this->assertEquals(['plan' => 'free'], $t->meta);
    }

    public function testFromRowDefaults(): void
    {
        $t = Tenant::fromRow(['id' => '1', 'name' => 'X', 'slug' => 'x']);
        $this->assertEquals(1, $t->id);
        $this->assertEquals('', $t->domain);
        $this->assertEquals('', $t->database);
        $this->assertEquals([], $t->meta);
    }

    public function testToArray(): void
    {
        $t = new Tenant(3, 'Gamma', 'gamma', 'gamma.io', '', true, [], '2026-03-01');
        $arr = $t->toArray();
        $this->assertEquals(3, $arr['id']);
        $this->assertEquals('gamma', $arr['slug']);
        $this->assertArrayHasKey('active', $arr);
        $this->assertArrayHasKey('meta', $arr);
    }

    public function testCachePrefix(): void
    {
        $t = new Tenant(42, 'Zeta', 'zeta');
        $this->assertEquals('tenant_42_', $t->cachePrefix());
    }

    public function testStoragePath(): void
    {
        $t = new Tenant(1, 'Acme', 'acme');
        $path = $t->storagePath('/var/storage');
        $this->assertEquals('/var/storage/tenants/acme', $path);
    }

    public function testStoragePathTrailingSlash(): void
    {
        $t = new Tenant(1, 'Acme', 'acme');
        $path = $t->storagePath('/var/storage/');
        $this->assertStringEndsWith('/tenants/acme', $path);
        $this->assertStringNotContainsString('//', $path);
    }

    public function testToString(): void
    {
        $t = new Tenant(1, 'Acme', 'acme');
        $this->assertEquals('acme', (string) $t);
    }

    public function testInactiveTenant(): void
    {
        $t = Tenant::fromRow(['id' => '1', 'name' => 'Old', 'slug' => 'old', 'active' => '0']);
        $this->assertFalse($t->active);
    }
}

// =============================================================================
// TenantResolver
// =============================================================================

class TenantResolverSubdomainTest extends TestCase
{
    private TenantResolver $resolver;

    public function setUp(): void
    {
        $this->resolver = new TenantResolver([
            'subdomain_base' => 'myapp.com',
            'identification' => ['subdomain'],
        ]);
    }

    public function testSimpleSubdomain(): void
    {
        $this->assertEquals('acme', $this->resolver->fromSubdomain('acme.myapp.com'));
    }

    public function testSubdomainWithPort(): void
    {
        $this->assertEquals('acme', $this->resolver->fromSubdomain('acme.myapp.com:8080'));
    }

    public function testBaseOnlyReturnsNull(): void
    {
        $this->assertNull($this->resolver->fromSubdomain('myapp.com'));
    }

    public function testDifferentBaseReturnsNull(): void
    {
        $this->assertNull($this->resolver->fromSubdomain('acme.other.com'));
    }

    public function testNestedSubdomainIgnored(): void
    {
        $this->assertNull($this->resolver->fromSubdomain('a.b.myapp.com'));
    }

    public function testCaseInsensitive(): void
    {
        $this->assertEquals('acme', $this->resolver->fromSubdomain('ACME.MyApp.com'));
    }
}

class TenantResolverHeaderTest extends TestCase
{
    private TenantResolver $resolver;

    public function setUp(): void
    {
        $this->resolver = new TenantResolver(['header_key' => 'X-Tenant-ID']);
    }

    public function testHeaderResolved(): void
    {
        $slug = $this->resolver->fromHeader(['x-tenant-id' => 'acme']);
        $this->assertEquals('acme', $slug);
    }

    public function testMissingHeaderReturnsNull(): void
    {
        $this->assertNull($this->resolver->fromHeader([]));
    }

    public function testHeaderTrimmed(): void
    {
        $slug = $this->resolver->fromHeader(['x-tenant-id' => '  beta  ']);
        $this->assertEquals('beta', $slug);
    }
}

class TenantResolverPathTest extends TestCase
{
    private TenantResolver $resolver;

    public function setUp(): void
    {
        $this->resolver = new TenantResolver(['path_segment' => 1]);
    }

    public function testFirstSegment(): void
    {
        $this->assertEquals('acme', $this->resolver->fromPath('/acme/dashboard'));
    }

    public function testTrailingSlash(): void
    {
        $this->assertEquals('acme', $this->resolver->fromPath('/acme/'));
    }

    public function testEmptyPathReturnsNull(): void
    {
        $this->assertNull($this->resolver->fromPath('/'));
    }

    public function testSecondSegment(): void
    {
        $r = new TenantResolver(['path_segment' => 2]);
        $this->assertEquals('dashboard', $r->fromPath('/acme/dashboard'));
    }

    public function testLowercased(): void
    {
        $this->assertEquals('acme', $this->resolver->fromPath('/ACME/stuff'));
    }
}

class TenantResolverQueryTest extends TestCase
{
    private TenantResolver $resolver;

    public function setUp(): void
    {
        $this->resolver = new TenantResolver();
    }

    public function testQueryParam(): void
    {
        $this->assertEquals('acme', $this->resolver->fromQuery(['tenant' => 'acme']));
    }

    public function testMissingParamReturnsNull(): void
    {
        $this->assertNull($this->resolver->fromQuery([]));
    }

    public function testQueryTrimmedAndLowercased(): void
    {
        $this->assertEquals('acme', $this->resolver->fromQuery(['tenant' => '  ACME  ']));
    }
}

class TenantResolverExclusionTest extends TestCase
{
    private TenantResolver $resolver;

    public function setUp(): void
    {
        $this->resolver = new TenantResolver([
            'subdomain_base' => 'myapp.com',
            'identification' => ['subdomain', 'path', 'query'],
            'excluded'       => ['www', 'api', 'admin'],
        ]);
    }

    public function testExcludedSubdomainSkipped(): void
    {
        // www is excluded; path '/' has no segment, query is empty — nothing resolves
        $slug = $this->resolver->resolve('www.myapp.com', '/', [], []);
        $this->assertNull($slug);
    }

    public function testExcludedPathSlugSkipped(): void
    {
        $slug = $this->resolver->resolve('myapp.com', '/api/orders', [], []);
        $this->assertNull($slug);
    }

    public function testValidTenantResolves(): void
    {
        $slug = $this->resolver->resolve('acme.myapp.com', '/', [], []);
        $this->assertEquals('acme', $slug);
    }

    public function testDriverPriorityOrder(): void
    {
        // Subdomain wins over path when both are present
        $slug = $this->resolver->resolve('acme.myapp.com', '/beta/page', [], []);
        $this->assertEquals('acme', $slug);
    }
}

// =============================================================================
// TenantManager — static context
// =============================================================================

class TenantManagerContextTest extends TestCase
{
    public function setUp(): void
    {
        TenantManager::reset();
    }

    public function tearDown(): void
    {
        TenantManager::reset();
    }

    public function testInitiallyNoTenant(): void
    {
        $this->assertNull(TenantManager::current());
        $this->assertNull(TenantManager::id());
        $this->assertFalse(TenantManager::check());
    }

    public function testSetCurrent(): void
    {
        $t = new Tenant(5, 'Demo', 'demo');
        TenantManager::setCurrent($t);
        $this->assertSame($t, TenantManager::current());
        $this->assertEquals(5, TenantManager::id());
        $this->assertTrue(TenantManager::check());
    }

    public function testForget(): void
    {
        $t = new Tenant(5, 'Demo', 'demo');
        TenantManager::setCurrent($t);
        TenantManager::forget();
        $this->assertNull(TenantManager::current());
        $this->assertFalse(TenantManager::check());
    }

    public function testCachePrefixWithTenant(): void
    {
        $t = new Tenant(9, 'Nine', 'nine');
        TenantManager::setCurrent($t);
        $this->assertEquals('tenant_9_', TenantManager::cachePrefix());
    }

    public function testCachePrefixWithoutTenant(): void
    {
        $this->assertEquals('', TenantManager::cachePrefix());
    }

    public function testStoragePathWithTenant(): void
    {
        $t = new Tenant(9, 'Nine', 'nine');
        TenantManager::setCurrent($t);
        $path = TenantManager::storagePath('/storage');
        $this->assertStringEndsWith('/tenants/nine', $path);
    }
}

// =============================================================================
// TenantManager — slugify
// =============================================================================

class TenantManagerSlugifyTest extends TestCase
{
    public function testSimpleSlug(): void
    {
        $this->assertEquals('acme-corp', TenantManager::slugify('Acme Corp'));
    }

    public function testSpecialChars(): void
    {
        $this->assertEquals('hello-world', TenantManager::slugify('Hello & World!'));
    }

    public function testLeadingTrailingDashes(): void
    {
        $slug = TenantManager::slugify('--acme--');
        $this->assertNotEquals('-', substr($slug, 0, 1));
        $this->assertNotEquals('-', substr($slug, -1));
    }

    public function testAlreadySlug(): void
    {
        $this->assertEquals('acme', TenantManager::slugify('acme'));
    }

    public function testNumbers(): void
    {
        $this->assertEquals('tenant-2', TenantManager::slugify('Tenant 2'));
    }
}

// =============================================================================
// TenantManager — CRUD (SQLite in-memory)
// =============================================================================

class TenantManagerCrudTest extends TestCase
{
    public function setUp(): void
    {
        TenantManager::reset();
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        Database::setPdo($pdo);
        TenantManager::ensureTable();
    }

    public function tearDown(): void
    {
        TenantManager::reset();
        Database::disconnect();
    }

    public function testCreateAndFind(): void
    {
        $tenant = TenantManager::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.com']);
        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertGreaterThan(0, $tenant->id);
        $this->assertEquals('Acme', $tenant->name);
        $this->assertEquals('acme', $tenant->slug);

        $found = TenantManager::find($tenant->id);
        $this->assertNotNull($found);
        $this->assertEquals('acme', $found->slug);
    }

    public function testFindBySlug(): void
    {
        TenantManager::create(['name' => 'Beta', 'slug' => 'beta']);
        $t = TenantManager::findBySlug('beta');
        $this->assertNotNull($t);
        $this->assertEquals('Beta', $t->name);
    }

    public function testFindByDomain(): void
    {
        TenantManager::create(['name' => 'Gamma', 'slug' => 'gamma', 'domain' => 'gamma.io']);
        $t = TenantManager::findByDomain('gamma.io');
        $this->assertNotNull($t);
        $this->assertEquals('gamma', $t->slug);
    }

    public function testFindNonExistentReturnsNull(): void
    {
        $this->assertNull(TenantManager::find(9999));
        $this->assertNull(TenantManager::findBySlug('nope'));
    }

    public function testAll(): void
    {
        TenantManager::create(['name' => 'A', 'slug' => 'a']);
        TenantManager::create(['name' => 'B', 'slug' => 'b']);
        $all = TenantManager::all();
        $this->assertCount(2, $all);
        $this->assertInstanceOf(Tenant::class, $all[0]);
    }

    public function testDelete(): void
    {
        $t = TenantManager::create(['name' => 'Del', 'slug' => 'del']);
        TenantManager::delete($t->id);
        $this->assertNull(TenantManager::find($t->id));
    }

    public function testActivateDeactivate(): void
    {
        $t = TenantManager::create(['name' => 'Toggle', 'slug' => 'toggle']);
        TenantManager::deactivate($t->id);
        $this->assertFalse(TenantManager::find($t->id)->active);
        TenantManager::activate($t->id);
        $this->assertTrue(TenantManager::find($t->id)->active);
    }

    public function testCreateAutoSlugFromName(): void
    {
        $t = TenantManager::create(['name' => 'Hello World']);
        $this->assertEquals('hello-world', $t->slug);
    }

    public function testActiveTenantNotReturnedAfterDeactivate(): void
    {
        $t = TenantManager::create(['name' => 'Tmp', 'slug' => 'tmp', 'domain' => 'tmp.io']);
        TenantManager::deactivate($t->id);

        $resolver = new TenantResolver(['identification' => ['subdomain'], 'subdomain_base' => 'myapp.com']);
        TenantManager::setResolver($resolver);

        // identify() checks $tenant->active before setting current
        $found = TenantManager::identify('tmp.myapp.com', '/', ['x-tenant-id' => 'tmp'], []);
        $this->assertNull($found);
    }
}

// =============================================================================
// TenantManager — identify() integration
// =============================================================================

class TenantManagerIdentifyTest extends TestCase
{
    public function setUp(): void
    {
        TenantManager::reset();
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        Database::setPdo($pdo);
        TenantManager::ensureTable();
        TenantManager::create(['name' => 'Acme', 'slug' => 'acme', 'domain' => 'acme.com']);
    }

    public function tearDown(): void
    {
        TenantManager::reset();
        Database::disconnect();
    }

    public function testIdentifyBySubdomain(): void
    {
        $resolver = new TenantResolver(['identification' => ['subdomain'], 'subdomain_base' => 'myapp.com']);
        TenantManager::setResolver($resolver);

        // acme.myapp.com → slug 'acme' → findBySlug('acme') → tenant
        $tenant = TenantManager::identify('acme.myapp.com', '/', [], []);
        $this->assertNotNull($tenant);
        $this->assertEquals('acme', $tenant->slug);
    }

    public function testIdentifyByHeader(): void
    {
        $resolver = new TenantResolver(['identification' => ['header'], 'header_key' => 'X-Tenant-ID']);
        TenantManager::setResolver($resolver);

        $tenant = TenantManager::identify('', '/', ['x-tenant-id' => 'acme'], []);
        $this->assertNotNull($tenant);
        $this->assertEquals('acme', $tenant->slug);
        $this->assertSame($tenant, TenantManager::current());
    }

    public function testUnknownTenantReturnsNull(): void
    {
        $resolver = new TenantResolver(['identification' => ['header'], 'header_key' => 'X-Tenant-ID']);
        TenantManager::setResolver($resolver);

        $result = TenantManager::identify('', '/', ['x-tenant-id' => 'unknown'], []);
        $this->assertNull($result);
        $this->assertNull(TenantManager::current());
    }
}

// =============================================================================
// TenantScope trait
// =============================================================================

/** Minimal model that uses TenantScope for tests */
class ScopedOrder
{
    use TenantScope;

    public static string $table = 'scoped_orders';
    public array $attributes    = [];
}

class TenantScopeTest extends TestCase
{
    public function setUp(): void
    {
        TenantManager::reset();
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        Database::setPdo($pdo);
        TenantManager::ensureTable();

        Database::unprepared("CREATE TABLE scoped_orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id INTEGER NOT NULL,
            label TEXT NOT NULL
        )");
    }

    public function tearDown(): void
    {
        TenantManager::reset();
        Database::disconnect();
    }

    private function setTenant(int $id): void
    {
        TenantManager::setCurrent(new Tenant($id, "T{$id}", "t{$id}"));
    }

    public function testAllForTenantEmptyWhenNoTenant(): void
    {
        $this->assertEquals([], ScopedOrder::allForTenant());
    }

    public function testAllForTenantScoped(): void
    {
        $this->setTenant(1);
        ScopedOrder::createForTenant(['label' => 'T1-A']);
        ScopedOrder::createForTenant(['label' => 'T1-B']);

        $this->setTenant(2);
        ScopedOrder::createForTenant(['label' => 'T2-A']);

        // Switch back to tenant 1
        $this->setTenant(1);
        $rows = ScopedOrder::allForTenant();
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertEquals(1, $row['tenant_id']);
        }
    }

    public function testFindForTenantScoped(): void
    {
        $this->setTenant(1);
        $id = ScopedOrder::createForTenant(['label' => 'hello']);

        $this->setTenant(2);
        // Tenant 2 cannot see tenant 1's row
        $this->assertNull(ScopedOrder::findForTenant($id));

        $this->setTenant(1);
        $row = ScopedOrder::findForTenant($id);
        $this->assertNotNull($row);
        $this->assertEquals('hello', $row['label']);
    }

    public function testCountForTenant(): void
    {
        $this->setTenant(1);
        ScopedOrder::createForTenant(['label' => 'x']);
        ScopedOrder::createForTenant(['label' => 'y']);
        $this->assertEquals(2, ScopedOrder::countForTenant());

        $this->setTenant(2);
        $this->assertEquals(0, ScopedOrder::countForTenant());
    }

    public function testDeleteForTenant(): void
    {
        $this->setTenant(1);
        $id = ScopedOrder::createForTenant(['label' => 'to-delete']);
        ScopedOrder::deleteForTenant($id);
        $this->assertNull(ScopedOrder::findForTenant($id));
    }

    public function testDeleteForTenantCannotDeleteOtherTenant(): void
    {
        $this->setTenant(1);
        $id = ScopedOrder::createForTenant(['label' => 'protected']);

        $this->setTenant(2);
        ScopedOrder::deleteForTenant($id); // should be a no-op

        $this->setTenant(1);
        $this->assertNotNull(ScopedOrder::findForTenant($id));
    }

    public function testCreateForTenantThrowsWithNoTenant(): void
    {
        $this->expectException(\RuntimeException::class);
        ScopedOrder::createForTenant(['label' => 'fail']);
    }

    public function testTenantScopeClause(): void
    {
        $this->setTenant(7);
        $scope = ScopedOrder::tenantScope();
        $this->assertEquals('tenant_id = ?', $scope['clause']);
        $this->assertEquals([7], $scope['bindings']);
    }

    public function testTenantScopeNoTenant(): void
    {
        $scope = ScopedOrder::tenantScope();
        $this->assertEquals('1=1', $scope['clause']);
        $this->assertEquals([], $scope['bindings']);
    }

    public function testFillTenantId(): void
    {
        $this->setTenant(5);
        $order = new ScopedOrder();
        $order->fillTenantId();
        $this->assertEquals(5, $order->attributes['tenant_id']);
    }

    public function testFillTenantIdDoesNotOverwrite(): void
    {
        $this->setTenant(5);
        $order = new ScopedOrder();
        $order->attributes['tenant_id'] = 99;
        $order->fillTenantId();
        $this->assertEquals(99, $order->attributes['tenant_id']); // unchanged
    }

    public function testCurrentTenantId(): void
    {
        $this->setTenant(42);
        $this->assertEquals(42, ScopedOrder::currentTenantId());
    }

    public function testCurrentTenantIdNullWhenNotSet(): void
    {
        $this->assertNull(ScopedOrder::currentTenantId());
    }
}

// =============================================================================
// TenantScope — getTable() derivation
// =============================================================================

class TablelessModel
{
    use TenantScope;
    // no $table property — will be derived from class name
}

class TenantScopeTableDerivationTest extends TestCase
{
    public function testTableDerivedFromClassName(): void
    {
        // TablelessModel → 'tableless_models'
        $scope = TablelessModel::tenantScope();
        // Invoking tenantScope() exercises getTable() indirectly — no exception thrown means ok
        $this->assertArrayHasKey('clause', $scope);
    }
}

// =============================================================================
// TenantAwareCache
// =============================================================================

class TenantAwareCacheTest extends TestCase
{
    private string $tmpDir;

    public function setUp(): void
    {
        TenantManager::reset();
        // Inject a fresh file cache driver into the static Cache class for isolation
        $this->tmpDir = sys_get_temp_dir() . '/nemesis_cache_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $driver = new \Nemesis\Core\Cache\FileCacheDriver($this->tmpDir);
        $ref  = new \ReflectionClass(\Nemesis\Core\Cache::class);
        $prop = $ref->getProperty('driver');
        $prop->setAccessible(true);
        $prop->setValue(null, $driver);
    }

    public function tearDown(): void
    {
        TenantManager::reset();
        // Clear the static driver so subsequent tests get a fresh instance
        $ref  = new \ReflectionClass(\Nemesis\Core\Cache::class);
        $prop = $ref->getProperty('driver');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
        // Clean up temp files
        foreach (glob($this->tmpDir . '/*.cache') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->tmpDir);
    }

    public function testPrefixedKey(): void
    {
        $cache = new TenantAwareCache('test_prefix_');
        $this->assertEquals('test_prefix_orders', $cache->prefixed('orders'));
    }

    public function testGetPrefix(): void
    {
        $cache = new TenantAwareCache('pfx_');
        $this->assertEquals('pfx_', $cache->getPrefix());
    }

    public function testForTenantFactory(): void
    {
        $cache = TenantAwareCache::forTenant(7);
        $this->assertEquals('tenant_7_', $cache->getPrefix());
    }

    public function testRawFactory(): void
    {
        $cache = TenantAwareCache::raw();
        $this->assertEquals('', $cache->getPrefix());
    }

    public function testCurrentFactoryWithTenant(): void
    {
        TenantManager::setCurrent(new Tenant(3, 'Three', 'three'));
        $cache = TenantAwareCache::current();
        $this->assertEquals('tenant_3_', $cache->getPrefix());
    }

    public function testCurrentFactoryWithoutTenant(): void
    {
        $cache = TenantAwareCache::current();
        $this->assertEquals('', $cache->getPrefix());
    }

    public function testSetAndGet(): void
    {
        $cache = TenantAwareCache::forTenant(1);
        $cache->set('key', 'value', 60);
        $this->assertEquals('value', $cache->get('key'));
    }

    public function testGetDefault(): void
    {
        $cache = TenantAwareCache::forTenant(1);
        $this->assertEquals('default', $cache->get('missing', 'default'));
    }

    public function testForget(): void
    {
        $cache = TenantAwareCache::forTenant(1);
        $cache->set('key', 'value', 60);
        $cache->forget('key');
        $this->assertNull($cache->get('key'));
    }

    public function testTenantIsolation(): void
    {
        $c1 = TenantAwareCache::forTenant(1);
        $c2 = TenantAwareCache::forTenant(2);

        $c1->set('counter', 100, 60);
        $c2->set('counter', 200, 60);

        $this->assertEquals(100, $c1->get('counter'));
        $this->assertEquals(200, $c2->get('counter'));
    }

    public function testSetMany(): void
    {
        $cache = TenantAwareCache::forTenant(5);
        $cache->setMany(['a' => 1, 'b' => 2], 60);
        $this->assertEquals(1, $cache->get('a'));
        $this->assertEquals(2, $cache->get('b'));
    }

    public function testGetMany(): void
    {
        $cache = TenantAwareCache::forTenant(5);
        $cache->set('x', 10, 60);
        $cache->set('y', 20, 60);
        $result = $cache->getMany(['x', 'y', 'z'], null);
        $this->assertEquals(10, $result['x']);
        $this->assertEquals(20, $result['y']);
        $this->assertNull($result['z']);
    }

    public function testForgetMany(): void
    {
        $cache = TenantAwareCache::forTenant(5);
        $cache->set('a', 1, 60);
        $cache->set('b', 2, 60);
        $cache->forgetMany(['a', 'b']);
        $this->assertNull($cache->get('a'));
        $this->assertNull($cache->get('b'));
    }

    public function testHas(): void
    {
        $cache = TenantAwareCache::forTenant(6);
        $this->assertFalse($cache->has('foo'));
        $cache->set('foo', 'bar', 60);
        $this->assertTrue($cache->has('foo'));
    }

    public function testRemember(): void
    {
        $cache   = TenantAwareCache::forTenant(7);
        $called  = 0;
        $value   = $cache->remember('expensive', 60, function () use (&$called) {
            $called++;
            return 'computed';
        });
        $this->assertEquals('computed', $value);
        $this->assertEquals(1, $called);

        // Second call should hit cache
        $value2 = $cache->remember('expensive', 60, function () use (&$called) {
            $called++;
            return 'recomputed';
        });
        $this->assertEquals('computed', $value2);
        $this->assertEquals(1, $called); // callback not called again
    }

    public function testForever(): void
    {
        $cache = TenantAwareCache::forTenant(8);
        $cache->forever('permanent', 'stays');
        $this->assertEquals('stays', $cache->get('permanent'));
    }
}

// =============================================================================
// Config file
// =============================================================================

class TenancyConfigTest extends TestCase
{
    private array $config;

    public function setUp(): void
    {
        $path = defined('NEMESIS_BASE_PATH')
            ? NEMESIS_BASE_PATH . '/config/tenancy.php'
            : __DIR__ . '/../../config/tenancy.php';
        $this->config = file_exists($path) ? (array) require $path : [];
    }

    public function testConfigExists(): void
    {
        $this->assertNotEmpty($this->config);
    }

    public function testStrategyKey(): void
    {
        $this->assertArrayHasKey('strategy', $this->config);
        $this->assertContains($this->config['strategy'], ['shared_database', 'database_per_tenant']);
    }

    public function testIdentificationKey(): void
    {
        $this->assertArrayHasKey('identification', $this->config);
        $this->assertIsArray($this->config['identification']);
    }

    public function testSubdomainBaseKey(): void
    {
        $this->assertArrayHasKey('subdomain_base', $this->config);
    }

    public function testExcludedKey(): void
    {
        $this->assertArrayHasKey('excluded', $this->config);
        $this->assertIsArray($this->config['excluded']);
    }

    public function testDbPerTenantKey(): void
    {
        $this->assertArrayHasKey('db_per_tenant', $this->config);
        $this->assertArrayHasKey('driver', $this->config['db_per_tenant']);
    }
}

// =============================================================================
// TenantMiddleware
// =============================================================================

class TenantMiddlewareTest extends TestCase
{
    public function setUp(): void
    {
        TenantManager::reset();
    }

    public function tearDown(): void
    {
        TenantManager::reset();
    }

    public function testMiddlewareClassExists(): void
    {
        $this->assertTrue(class_exists(\Nemesis\Http\Middleware\TenantMiddleware::class));
    }

    public function testMiddlewareImplementsInterface(): void
    {
        $mw = new \Nemesis\Http\Middleware\TenantMiddleware();
        $this->assertInstanceOf(\Nemesis\Contracts\MiddlewareInterface::class, $mw);
    }

    public function testMiddlewareHasHandleMethod(): void
    {
        $this->assertTrue(method_exists(\Nemesis\Http\Middleware\TenantMiddleware::class, 'handle'));
    }
}

// =============================================================================
// TenantManager — ensureTable (idempotent)
// =============================================================================

class TenantManagerEnsureTableTest extends TestCase
{
    public function setUp(): void
    {
        TenantManager::reset();
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        Database::setPdo($pdo);
    }

    public function tearDown(): void
    {
        TenantManager::reset();
        Database::disconnect();
    }

    public function testEnsureTableIsIdempotent(): void
    {
        TenantManager::ensureTable();
        TenantManager::ensureTable(); // should not throw
        $this->assertTrue(true);
    }

    public function testTableHasExpectedColumns(): void
    {
        TenantManager::ensureTable();
        $pdo  = Database::getPdo();
        $stmt = $pdo->query("PRAGMA table_info(tenants)");
        $cols = array_column($stmt->fetchAll(), 'name');
        foreach (['id', 'name', 'slug', 'domain', 'active', 'meta', 'created_at'] as $col) {
            $this->assertContains($col, $cols, "Missing column: {$col}");
        }
    }
}
