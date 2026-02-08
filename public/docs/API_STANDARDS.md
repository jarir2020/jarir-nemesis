# API Standards - Usage Guide

## ApiResponse Helper

The `ApiResponse` class provides standardized JSON responses for your API.

### Success Responses

```php
use Nemesis\Http\ApiResponse;

// Simple success
ApiResponse::success(['users' => $users], 'Users retrieved successfully');

// Returns:
// {
//     "success": true,
//     "message": "Users retrieved successfully",
//     "data": { "users": [...] }
// }

// Paginated response
$paginator = Post::paginate(10);
ApiResponse::paginated($paginator, 'Posts retrieved');
```

### Error Responses

```php
// General error
ApiResponse::error('Something went wrong', 500);

// Specific helpers
ApiResponse::notFound('User not found');           // 404
ApiResponse::unauthorized('Invalid token');        // 401
ApiResponse::forbidden('Insufficient permissions'); // 403
ApiResponse::serverError('Database error');        // 500

// Validation errors
ApiResponse::validationError([
    'email' => 'Invalid email format',
    'password' => 'Password must be at least 8 characters'
]);
```

### Controller Integration

```php
class UserController extends Controller {
    public function index() {
        $users = User::all();
        return ApiResponse::success($users, 'Users retrieved');
    }

    public function show($id) {
        $user = User::find($id);
        if (!$user) {
            return ApiResponse::notFound('User not found');
        }
        return ApiResponse::success($user);
    }

    public function store() {
        $request = new Request();
        $validated = $request->validate([
            'email' => 'required|email',
            'name' => 'required'
        ]);
        
        if (!$validated) {
            return ApiResponse::validationError($request->errors());
        }

        $user = User::create($validated);
        return ApiResponse::success($user, 'User created', 201);
    }
}
```

## CORS Middleware

Enable cross-origin requests for your API.

### Basic Setup (Allow All Origins)

In `routes/api.php` or your bootstrap file:

```php
use Nemesis\Middleware\CorsMiddleware;

// Add as global middleware
$cors = new CorsMiddleware();
// Apply to all API routes
```

### Specific Origins

```php
$cors = new CorsMiddleware([
    'origins' => [
        'http://localhost:3000',
        'https://app.yourdomain.com',
        'https://admin.yourdomain.com'
    ],
    'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
    'headers' => ['Content-Type', 'Authorization', 'X-API-Key'],
    'credentials' => true,  // Allow cookies/auth headers
    'max_age' => 86400      // Cache preflight for 24 hours
]);
```

### Production Configuration

```php
// In config/cors.php
return [
    'origins' => explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: '*'),
    'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
    'headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
    'credentials' => getenv('CORS_ALLOW_CREDENTIALS') === 'true',
    'max_age' => 86400
];

// Usage
$config = require __DIR__ . '/../config/cors.php';
$cors = new CorsMiddleware($config);
```

### Frontend Integration Example

```javascript
// React/Vue/Angular frontend
fetch('http://api.yourdomain.com/users', {
    method: 'GET',
    headers: {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer ' + token
    },
    credentials: 'include'  // if using credentials: true
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log(data.data);
    } else {
        console.error(data.message, data.errors);
    }
});
```

## Benefits

✅ **Consistent API responses** - Same structure across all endpoints  
✅ **Better error handling** - Standardized error codes and messages  
✅ **Frontend-ready** - Works seamlessly with modern SPAs  
✅ **Cross-origin support** - Enable requests from different domains  
✅ **Secure** - Configurable origins prevent unauthorized access  
✅ **Production-ready** - Environment-based configuration support
