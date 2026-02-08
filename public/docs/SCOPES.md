# Query Scopes Documentation

Query scopes allow you to define common sets of constraints that you may easily re-use throughout your application. Nemesis supports both Global and Local scopes.

---

## Global Scopes

Global scopes allow you to add constraints to every query for a given model. They are defined in the `boot` method of your model.

### Defining a Global Scope

```php
namespace App\Models;

use Nemesis\Core\Model;

class User extends Model {
    protected static function boot() {
        parent::boot();

        // All queries on the User model will include this constraint
        static::addGlobalScope('active', function ($builder) {
            $builder->where('active', 1);
        });
    }
}
```

**Usage:**
```php
// SELECT * FROM users WHERE active = 1
$users = User::all();
```

---

## Local Scopes

Local scopes allow you to define common sets of constraints that you can apply manually when querying.

### Defining a Local Scope
To define a local scope, prefix a model method with `scope`. The method should receive the query builder instance.

```php
class User extends Model {
    /**
     * Scope a query to only include "popular" users.
     */
    public function scopePopular($builder, $votes = 100) {
        return $builder->where('votes', '>', $votes);
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($builder) {
        return $builder->where('status', 'active');
    }
}
```

### Using a Local Scope
Once the scope has been defined, you may call the scope method when querying the model. You should not include the `scope` prefix when calling the method.

```php
// Apply a single scope
$users = User::popular()->get();

// Pass parameters to the scope
$topUsers = User::popular(500)->get();

// Chain multiple scopes
$activePopularUsers = User::active()->popular()->get();
```

---

## Best Practices

1. **Keep it Reusable**: Use scopes for any logic that appears in multiple places in your application.
2. **Descriptive Names**: Use clear names like `scopePublished` or `scopeDraft` instead of vague ones.
3. **Avoid Complex Logic**: Keep scopes simple. If the logic is too complex, consider using a Repository or a dedicated Query class.
