# Query Builder Documentation

Nemesis provides a fluent query builder that allows you to build and execute database queries with ease. It uses PHP's PDO internally and provides a clean, expressive syntax.

When `vendor:compress` is used, query-related classes and database helpers that are referenced by the application graph are preserved and will not be removed.

---

## Getting Started

To start building a query, you can use either the ` Nemesis\Core\Database::table()` method or instantiate the `Nemesis\Core\Fluent` class directly.

```php
use Nemesis\Core\Database;

$users = Database::table('users')->get();
```

---

## Retrieval Methods

### Fetching All Records
The `get` or `all` methods return an array of results.
```php
$users = Database::table('users')->get();
// OR
$users = Database::table('users')->all();
```

### Fetching a Single Record
The `first` method returns a single associative array if found, or `null`.
```php
$user = Database::table('users')->where('id', 1)->first();
```

### Finding by ID
The `find` method is a shortcut for finding a record by its primary key.
```php
$user = Database::table('users')->find(5); // Finds by 'id' = 5
$user = Database::table('users')->find('unique_code', 'code'); // Finds by 'code' = 'unique_code'
```

---

## Where Clauses

### Simple Where
```php
$users = Database::table('users')->where('active', 1)->get();
// Equivalent to
$users = Database::table('users')->where('active', '=', 1)->get();
```

### Multiple Conditions
Conditions are combined using `AND` by default.
```php
$users = Database::table('users')
    ->where('status', 'active')
    ->where('age', '>', 21)
    ->get();
```

### OR Where
```php
$users = Database::table('users')
    ->where('role', 'admin')
    ->orWhere('role', 'moderator')
    ->get();
```

---

## Ordering and Pagination

### Ordering
```php
$posts = Database::table('posts')
    ->orderBy('created_at', 'DESC')
    ->get();
```

### Limit and Offset
```php
$posts = Database::table('posts')
    ->limit(10)
    ->offset(20)
    ->get();
```

### Pagination
The `paginate` method handles limit, offset, and returns a `Paginator` object.
```php
$paginator = Database::table('posts')->paginate(15);

$items = $paginator->items();
$total = $paginator->total();
$lastPage = $paginator->lastPage();
```

---

## Inserts, Updates, and Deletes

### Insert
The `insert` method returns the ID of the newly created record.
```php
$userId = Database::table('users')->insert([
    'email' => 'john@example.com',
    'password' => password_hash('secret', PASSWORD_BCRYPT)
]);
```

### Update
The `update` method requires a `where` clause.
```php
Database::table('users')
    ->where('id', 1)
    ->update(['status' => 'inactive']);
```

### Delete
The `delete` method also requires a `where` clause.
```php
Database::table('users')
    ->where('id', 1)
    ->delete();
```

---

## Joins and Grouping

### Joins
Currently, the Query Builder supports `INNER JOIN`.
```php
$results = Database::table('users')
    ->join('profiles', 'users.id', '=', 'profiles.user_id')
    ->get();
```

### Group By
```php
$results = Database::table('orders')
    ->groupBy('user_id')
    ->get();
```

---

## Aggregates

You can perform count, max, and min operations directly.
```php
$count = Database::table('users')->count();
$maxPrice = Database::table('products')->max('price');
$minStock = Database::table('products')->min('stock');
```

---

## Raw Database Operations

If you need to run raw SQL, use the `Database` methods directly:
```php
use Nemesis\Core\Database;

$results = Database::view("SELECT * FROM users WHERE active = :active", ['active' => 1]);
$affected = Database::create("INSERT INTO logs (message) VALUES (?)", ['Log message']);
$affected = Database::update("UPDATE users SET status = ? WHERE id = ?", ['active', 5]);
$id = Database::delete("DELETE FROM users WHERE id = ?", [10]); // Note: delete() returns lastInsertId()
```

---

## Transactions

```php
Database::transaction(function() {
    Database::table('accounts')->where('id', 1)->update(['balance' => 900]);
    Database::table('accounts')->where('id', 2)->update(['balance' => 1100]);
});
```
