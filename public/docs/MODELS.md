# Models (ORM) Documentation

## Overview

Nemesis provides an Eloquent-style ORM for working with databases using object-oriented syntax. Each database table has a corresponding Model inheriting from `Nemesis\Core\Model`.

When `vendor:compress` is used, models that are imported by the application graph or referenced by database bootstrapping are treated as preserved roots and must not be removed.

---

## Creating Models

```bash
# Create a standard model
php nemesis make:model User

# Show configured database connections
php nemesis make:model User --list-connections

# Pin a generated model to a named connection
php nemesis make:model AnalyticsEvent --connection=analytics
```

---

## Model Definition

By default, Models assume a table name that is the pluralized, lowercase version of the class name (e.g., `User` -> `users`).

```php
<?php
namespace App\Models;

use Nemesis\Core\Model;

class User extends Model {
    protected $table = 'users'; // Optional if follows convention
    protected $primaryKey = 'id'; // Default is 'id'
    protected ?string $connection = 'analytics'; // Optional named connection

    // Mass assignment protection
    protected $fillable = ['name', 'email', 'password'];
    
    // If you want to guard everything except specific fields:
    // protected $guarded = ['id', 'is_admin'];
}
```

If you prefer to generate a model already wired to a named database connection, pass `--connection=name` to `make:model`. The generated stub will include a constructor-aware connection property so the model, builder, and fluent queries all use the same connection.

---

## CRUD Operations

### Create and Save

```php
// Creating a new instance manually
$user = new User();
$user->name = 'John Doe';
$user->email = 'john@example.com';
$user->save();

// The ID is automatically set after save
echo $user->id;
```

### Retrieval

```php
// Find by primary key
$user = User::find(1);

// Get all records
$users = User::all();

// Basic query
$activeUsers = User::where('active', 1)->get();

// First matching record
$user = User::where('email', 'admin@example.com')->first();
```

### Update

```php
$user = User::find(1);
$user->name = 'Jane Doe';
$user->save();

// Mass update
User::where('status', 'pending')->update(['status' => 'active']);
```

### Delete

```php
$user = User::find(1);
$user->delete();
```

---

## Model Events

Nemesis Models support lifecycle events. You can register callbacks in your model's `boot` method or via an Observer.

### Supported Events
- `creating`, `created`
- `updating`, `updated`
- `saving`, `saved`
- `deleting`, `deleted`

### Registering Callbacks

```php
class User extends Model {
    protected static function boot() {
        parent::boot();

        static::creating(function ($user) {
            // Logic before user is created
        });
    }
}
```

### Using Observers

```php
// Register in a ServiceProvider or bootstrap file
User::observe(UserObserver::class);
```

---

## Timestamps

By default, Nemesis expects `created_at` and `updated_at` columns on your tables. You can disable this by setting:

```php
class User extends Model {
    public $timestamps = false;
}
```
