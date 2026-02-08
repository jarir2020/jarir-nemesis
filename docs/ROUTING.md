# Routing Documentation

## Overview

Nemesis uses a performant tree-based router that supports dynamic parameters, middleware, route groups, and multiple HTTP methods. Routes are defined in `routes/route.php`.

You can use the `$router` instance directly, or the `Nemesis\Support\Facades\Route` facade for cleaner syntax.

---

## Basic Routing

### Defining Routes

```php
use Nemesis\Support\Facades\Route;

// GET route
Route::get('/', function($request) {
    return 'Welcome to Nemesis!';
});

// POST route
Route::post('/submit', [FormController::class, 'store']);

// Multiple methods (using $router instance)
$router->add('GET', '/webhook', [WebhookController::class, 'verify']);
```

### Available HTTP Methods

- `Route::get($uri, $action)`
- `Route::post($uri, $action)`
- `Route::put($uri, $action)`
- `Route::patch($uri, $action)`
- `Route::delete($uri, $action)`
- `Route::options($uri, $action)`

---

## Route Parameters

### Dynamic Segments

```php
Route::get('/user/{id}', function($request, $id) {
    return "User ID: {$id}";
});

Route::get('/post/{id}/comment/{commentId}', 
    function($request, $id, $commentId) {
        return "Post {$id}, Comment {$commentId}";
    }
);
```

---

## Route Groups

Route groups allow you to share attributes, such as middleware or prefixes, across a large number of routes without needing to define those attributes on each individual route.

### Middleware & Prefix

```php
Route::group(['middleware' => 'auth', 'prefix' => 'admin'], function($router) {
    $router->get('/dashboard', [AdminController::class, 'index']);
    $router->get('/users', [AdminController::class, 'users']);
});

// Access: /admin/dashboard
```

---

## Named Routes

Named routes allow the convenient generation of URLs or redirects for specific routes.

```php
Route::get('/user/profile', [UserProfileController::class, 'show'])->name('profile');

// Generating URLs (Helper function to be implemented)
// $url = route('profile');
```

---

## Route Middleware

### Applying Middleware

```php
Route::get('/admin', [AdminController::class, 'index'], ['auth']);

// Multiple middleware
Route::post('/api/data', [ApiController::class, 'store'], ['auth', 'throttle']);
```

### Available Middleware

- `auth` - Require authentication
- `guest` - Require guest (not authenticated)
- `csrf` - CSRF token verification
- `cors` - CORS headers
- `throttle` - Rate limiting (e.g., `throttle:60,1`)

---

## Fallback Routes

Define a route that will be executed when no other route matches the incoming request.

```php
Route::fallback(function() {
    return view('errors.404');
});
```

---

## Controller Actions

### Array Syntax (Recommended)

```php
Route::get('/users', [UserController::class, 'index']);
```

### Callable Syntax

```php
Route::get('/hello', function($request) {
    return 'Hello World!';
});
```

---

## Route Caching

Deploying to production? Cache your routes for better performance.

```bash
php nemesis route:cache
```

This creates `storage/framework/routes.php`. To clear the cache:

```bash
php nemesis route:clear
```

> **Note:** Closures cannot be cached. Use Controller classes for production routes.
