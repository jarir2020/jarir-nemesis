# Middleware Documentation

## Overview

Middleware provides a convenient mechanism for filtering HTTP requests entering your application. Middleware can inspect, modify, or reject requests before they reach your controllers.

Common uses include:
- Authentication (checking if user is logged in)
- CSRF Protection (verifying tokens)
- Logging (tracking request details)
- Input Trimming (cleaning up user input)

---

## Creating Middleware

### Generate Middleware

Use the CLI command to generate a new middleware class:

```bash
php nemesis make:middleware CheckAge
```

### Middleware Structure

A middleware class must define a `handle` method:

```php
<?php
namespace App\Http\Middleware;

class CheckAge {
    /**
     * Handle an incoming request.
     *
     * @param  \Nemesis\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, $next) {
        // Before logic: Perform checks
        if ($request->input('age') < 18) {
            return response()->json(['error' => 'Underage'], 403);
        }
        
        // Pass to next middleware/controller
        $response = $next($request);
        
        // After logic: Modify response
        return $response;
    }
}
```

---

## Registering Middleware

Middleware is registered in `app/Http/Kernel.php`.

### 1. Global Middleware
These run on **every** HTTP request to your application.

```php
protected $middleware = [
    \App\Http\Middleware\CheckForMaintenanceMode::class,
    \App\Http\Middleware\StartSession::class,
    \App\Http\Middleware\VerifyCsrfToken::class,
];
```

### 2. Route Middleware
These are assigned to specific routes via an alias key.

```php
protected $routeMiddleware = [
    'auth' => \App\Http\Middleware\Authenticate::class,
    'throttle' => \App\Http\Middleware\ThrottleRequests::class,
    'admin' => \App\Http\Middleware\CheckAdmin::class,
];
```

### 3. Middleware Groups
These allow you to assign multiple middleware to a route via a single key.

```php
protected $middlewareGroups = [
    'web' => [
        'encrypt_cookies',
        'cookie_serialization',
        'start_session',
        'verify_csrf_token',
    ],
    'api' => [
        'throttle:60,1',
        'auth:api',
    ],
];
```

---

## Assigning Middleware to Routes

### Single Middleware

```php
use Nemesis\Support\Facades\Route;

Route::get('/admin', [AdminController::class, 'index'], ['auth']);
```

### Multiple Middleware

```php
Route::get('/profile', [UserController::class, 'show'], ['auth', 'verified']);
```

### Using Groups in Routes

```php
// Apply 'web' group (defined in Kernel)
Route::group(['middleware' => 'web'], function($router) {
    $router->get('/', [HomeController::class, 'index']);
});
```

---

## Middleware Parameters

Middleware can accept additional parameters passed from the route.

### Defining Middleware with Parameters

Add arguments after the `$next` parameter:

```php
class CheckRole {
    public function handle($request, $next, $role) {
        if (!$request->user()->hasRole($role)) {
            // Redirect or error
        }
        return $next($request);
    }
}
```

### Passing Parameters in Routes

Separate the middleware name and parameters with a colon `:`. Multiple parameters should be separated by commas `,`.

```php
Route::get('/post/{id}', [PostController::class, 'show'], ['role:editor']);
```

---

## Built-in Middleware

Nemesis ships with several ready-to-use middleware:

- **`auth`**: Verifies the user is authenticated.
- **`guest`**: Verifies the user is NOT authenticated (useful for login pages).
- **`csrf`**: Protects against Cross-Site Request Forgery.
- **`throttle`**: Rate limits requests (e.g., `throttle:60,1` for 60 reqs/min).

---

## Best Practices

1. **Focused Responsibility**: Each middleware should do one thing well.
2. **Global vs Route**: Use Global middleware for critical app-wide logic (like Sessions), and Route middleware for specific logic (like Auth).
3. **Ordering**: The order in `Kernel.php` matters. Dependencies (like Session) should come before dependent middleware (like Auth).
