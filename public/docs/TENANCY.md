# Multi-Tenancy Documentation

Nemesis provides a lightweight system for building multi-tenant applications where data is segmented by a `tenant_id`.

---

## Tenant Manager

The `Nemesis\Tenancy\TenantManager` class handles the identification and storage of the current tenant context.

```php
use Nemesis\Tenancy\TenantManager;

// Set current tenant
TenantManager::setTenant(1);

// Get current tenant
$id = TenantManager::getTenant();
```

---

## Tenant Scope Trait

To automatically filter a model's queries by the current tenant and automatically assign the `tenant_id` on save, use the `TenantScope` trait.

### Usage in Model

```php
namespace App\Models;

use Nemesis\Core\Model;
use Nemesis\Tenancy\TenantScope;

class Post extends Model {
    use TenantScope;
}
```

### Automatic Filtering
When the trait is used, every query to the model will automatically include `WHERE tenant_id = ?`:

```php
// If current tenant is 1
$posts = Post::all(); // SELECT * FROM posts WHERE tenant_id = 1
```

### Automatic Assignment
When creating a new record, the `tenant_id` will be automatically set to the current tenant:

```php
$post = new Post();
$post->title = 'Hello';
$post->save(); // tenant_id is set automatically
```
