# Phase 21 — Multi-tenancy ✓

**Completed:** 2026-04-06  
**Tests:** 1131 total / 1131 passed (94 new in Phase 21)  
**Branch:** main

---

## What Was Built

| File | Purpose |
|---|---|
| `src/Tenancy/Tenant.php` | Value object DTO — id, name, slug, domain, database, active, meta |
| `src/Tenancy/TenantResolver.php` | Identifies tenant from subdomain, header, path, or query |
| `src/Tenancy/TenantManager.php` | Central hub — identify, CRUD, DB-per-tenant, context management |
| `src/Tenancy/TenantScope.php` | Trait for shared-DB models — scoped queries auto-injecting tenant_id |
| `src/Tenancy/TenantAwareCache.php` | Cache wrapper that namespaces all keys by tenant |
| `app/Http/Middleware/TenantMiddleware.php` | Resolves tenant before controllers; optional 'required' mode |
| `config/tenancy.php` | Full tenancy configuration with env-backed defaults |

---

## Strategies

### Strategy A — Shared Database (default)
One DB, every table carries a `tenant_id` column.

```php
// In your model:
class Order extends Model {
    use TenantScope;
}

// Queries are automatically scoped:
Order::allForTenant();           // SELECT * FROM orders WHERE tenant_id = ?
Order::findForTenant($id);       // SELECT * FROM orders WHERE id = ? AND tenant_id = ?
Order::countForTenant();
Order::createForTenant(['item' => 'Widget']);  // tenant_id auto-injected
Order::deleteForTenant($id);

// For custom queries:
$scope = Order::tenantScope();
// $scope = ['clause' => 'tenant_id = ?', 'bindings' => [7]]
```

### Strategy B — Database per Tenant
Each tenant gets its own PDO connection.

```php
TenantManager::switchConnection($tenant);
// All subsequent DB calls use the tenant's dedicated connection
```

---

## Tenant Identification

Resolves in configured priority order (set `TENANT_IDENTIFICATION` env):

```
subdomain → acme.myapp.com          → slug = 'acme'
header    → X-Tenant-ID: acme       → slug = 'acme'
path      → /acme/dashboard         → slug = 'acme'
query     → ?tenant=acme            → slug = 'acme'
```

---

## Usage

```php
// Bootstrap (in middleware or before request handling):
$tenant = TenantManager::identify(
    host:    $_SERVER['HTTP_HOST'],
    path:    $_SERVER['REQUEST_URI'],
    headers: getallheaders(),
    query:   $_GET,
);

// Access current tenant:
$tenant = TenantManager::current();  // ?Tenant
$id     = TenantManager::id();       // int|string|null
$active = TenantManager::check();    // bool

// Create a tenant:
$tenant = TenantManager::create(['name' => 'Acme Corp', 'domain' => 'acme.com']);

// Admin operations:
TenantManager::activate($id);
TenantManager::deactivate($id);
TenantManager::all();
TenantManager::findBySlug('acme');
TenantManager::findByDomain('acme.com');
TenantManager::delete($id);
```

---

## Tenant-Aware Cache

```php
// Scoped to current tenant (keys auto-prefixed with tenant_{id}_):
$cache = new TenantAwareCache();
$cache->set('orders', $orders, 300);
$cache->get('orders');
$cache->forget('orders');
$cache->remember('stats', 600, fn() => DB::computeStats());

// Factories:
TenantAwareCache::current();        // current tenant's namespace
TenantAwareCache::forTenant(7);    // explicit tenant ID
TenantAwareCache::raw();            // no prefix (global/admin cache)

// Bulk:
$cache->setMany(['a' => 1, 'b' => 2], 300);
$cache->getMany(['a', 'b', 'c']);
$cache->forgetMany(['a', 'b']);
```

---

## Middleware

```php
// Register in your route group:
->middleware('tenant')            // silent — allows unauthenticated tenant
->middleware('tenant:required')   // returns 404 if no tenant resolved
```

---

## CLI Commands

```bash
php nemesis tenant:create "Acme Corp" acme.com         # Create tenant
php nemesis tenant:list                                 # Show all tenants (table)
php nemesis tenant:activate 3                           # Activate tenant #3
php nemesis tenant:deactivate 3                         # Deactivate tenant #3
php nemesis tenant:delete 3                             # Delete tenant (confirms)
php nemesis tenant:migrate                              # Migrate all tenants
php nemesis tenant:migrate acme                         # Migrate single tenant
php nemesis tenant:seed                                 # Seed all tenants
php nemesis tenant:seed acme DatabaseSeeder             # Seed single tenant
```

---

## Config (`config/tenancy.php`)

```php
return [
    'strategy'       => env('TENANCY_STRATEGY', 'shared_database'),
    'identification' => ['subdomain', 'header', 'path'],
    'subdomain_base' => env('TENANT_SUBDOMAIN_BASE', 'example.com'),
    'header_key'     => env('TENANT_HEADER', 'X-Tenant-ID'),
    'path_segment'   => 1,
    'excluded'       => ['www', 'api', 'admin', 'app', 'mail', 'static', 'assets'],
    'db_per_tenant'  => [
        'driver' => env('TENANT_DB_DRIVER', 'sqlite'),
        'host'   => env('TENANT_DB_HOST', '127.0.0.1'),
        'prefix' => env('TENANT_DB_PREFIX', 'tenant_'),
        // ...
    ],
];
```

---

## Testing

```php
// Isolated tenancy tests:
$pdo = new PDO('sqlite::memory:');
Database::setPdo($pdo);
TenantManager::ensureTable();

$tenant = TenantManager::create(['name' => 'Test', 'slug' => 'test']);
TenantManager::setCurrent($tenant);

// Use TenantScope:
Order::createForTenant(['label' => 'x']);
$rows = Order::allForTenant();

// Tear down:
TenantManager::reset();
Database::disconnect();
```
