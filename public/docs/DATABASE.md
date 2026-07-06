# Database Documentation

## Overview

Nemesis provides a fluent query builder and Eloquent-style ORM for database operations. Supports MySQL, PostgreSQL, and SQLite.

Vendor compression preserves the ORM bootstrap, database configuration, migrations, and any database entrypoints that are referenced by the application graph.

---

## Query Builder

### Basic Queries

```php
use Nemesis\Database\DB;

// Select all
$users = DB::table('users')->get();

// Select with conditions
$active = DB::table('users')
    ->where('active', 1)
    ->get();

// Select single row
$user = DB::table('users')
    ->where('id', 1)
    ->first();
```

### Where Clauses

```php
// Simple where
DB::table('users')->where('status', 'active')->get();

// Multiple conditions
DB::table('users')
    ->where('status', 'active')
    ->where('age', '>=', 18)
    ->get();

// OR conditions
DB::table('users')
    ->where('role', 'admin')
    ->orWhere('role', 'moderator')
    ->get();

// Where In
DB::table('users')
    ->whereIn('id', [1, 2, 3])
    ->get();

// Where Null
DB::table('users')
    ->whereNull('deleted_at')
    ->get();
```

### Ordering and Limiting

```php
DB::table('posts')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

// Offset
DB::table('posts')
    ->orderBy('id')
    ->limit(10)
    ->offset(20)
    ->get();
```

### Joins

```php
DB::table('users')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->select('users.*', 'posts.title')
    ->get();

// Left Join
DB::table('users')
    ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
    ->get();
```

### Aggregates

```php
// Count
$count = DB::table('users')->count();

// Sum
$total = DB::table('orders')->sum('amount');

// Average
$avg = DB::table('products')->avg('price');

// Min/Max
$min = DB::table('products')->min('price');
$max = DB::table('products')->max('price');
```

---

## Insert, Update, Delete

### Insert

```php
// Insert single
DB::table('users')->insert([
    'name' => 'John',
    'email' => 'john@example.com'
]);

// Insert and get ID
$id = DB::table('users')->insertGetId([
    'name' => 'Jane',
    'email' => 'jane@example.com'
]);
```

### Update

```php
DB::table('users')
    ->where('id', 1)
    ->update(['status' => 'active']);

// Increment/Decrement
DB::table('posts')
    ->where('id', 1)
    ->increment('views');

DB::table('products')
    ->where('id', 1)
    ->decrement('stock', 5);
```

### Delete

```php
DB::table('users')
    ->where('id', 1)
    ->delete();

// Delete all
DB::table('temp_data')->delete();
```

---

## Raw Queries

```php
// Raw select
$users = DB::raw('SELECT * FROM users WHERE active = ?', [1]);

// Raw insert
DB::raw('INSERT INTO logs (message) VALUES (?)', ['User logged in']);

// Raw in query builder
DB::table('users')
    ->whereRaw('DATE(created_at) = CURDATE()')
    ->get();
```

---

## Transactions

```php
DB::beginTransaction();

try {
    DB::table('accounts')->where('id', 1)->decrement('balance', 100);
    DB::table('accounts')->where('id', 2)->increment('balance', 100);
    
    DB::commit();
} catch (\Exception $e) {
    DB::rollback();
    throw $e;
}
```

---

## Connection Management

```php
// Get connection
$db = DB::connection();

// Multiple connections
$mysql = DB::connection('mysql');
$postgres = DB::connection('pgsql');
```

### Discovering Available Connections

Use the CLI to inspect the named database targets configured for your app:

```bash
php nemesis db:list-connections
php nemesis make:model Invoice --connection=analytics
php nemesis make:model Invoice --list-connections
```

This is useful when your application has separate default, analytics, tenant, or archive databases and you want generated models to target one explicitly.

---

## Best Practices

1. **Use parameter binding** - Prevent SQL injection
2. **Use transactions** - For related operations
3. **Index your queries** - Add database indexes for performance
4. **Avoid N+1** - Use joins or eager loading
5. **Use query builder** - More readable than raw SQL
