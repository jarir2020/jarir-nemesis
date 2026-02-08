# Multi-Tenancy Documentation

## Overview

Multi-tenancy allows you to build SaaS applications where multiple customers (tenants) share the same application but have isolated data.

---

## Setting Tenant Context

```php
use Nemesis\Tenancy\TenantManager;

// Set current tenant
TenantManager::setTenant($tenantId);

// All subsequent queries are scoped to this tenant
$orders = Order::all(); // Automatically adds WHERE tenant_id = ?
```

---

## Tenant-Aware Models

```php
<?php
namespace App\Models;

use Nemesis\Database\Model;
use Nemesis\Tenancy\TenantScope;

class Order extends Model {
    protected static function boot() {
        parent::boot();
        static::addGlobalScope(new TenantScope());
    }
}
```

---

## Middleware

### Set Tenant from Subdomain

```php
class SetTenantFromSubdomain {
    public function handle($request, $next) {
        $subdomain = $this->getSubdomain($request);
        $tenant = Tenant::where('subdomain', $subdomain)->firstOrFail();
        
        TenantManager::setTenant($tenant->id);
        
        return $next($request);
    }
    
    protected function getSubdomain($request) {
        $host = $request->header('host');
        return explode('.', $host)[0];
    }
}
```

---

## Database Schema

### Tenant Table

```php
Schema::create('tenants', function($table) {
    $table->id();
    $table->string('name');
    $table->string('subdomain')->unique();
    $table->string('database')->nullable(); // For multi-database approach
    $table->timestamps();
});
```

### Tenant-Scoped Tables

```php
Schema::create('orders', function($table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->string('order_number');
    $table->decimal('total', 10, 2);
    $table->timestamps();
    
    $table->index('tenant_id');
});
```

---

## Tenant Isolation Strategies

### Single Database (Recommended)

- All tenants share one database
- `tenant_id` column on all tables
- Automatic query scoping

### Multi-Database

- Each tenant has separate database
- Complete data isolation
- More complex management

---

## Best Practices

1. **Always scope queries** - Use global scopes
2. **Index tenant_id** - Improve query performance
3. **Validate tenant** - Ensure user belongs to tenant
4. **Test isolation** - Verify data doesn't leak between tenants
5. **Backup per tenant** - Enable tenant-specific backups
