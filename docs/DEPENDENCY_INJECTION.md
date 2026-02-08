# Dependency Injection Documentation

## Overview

Nemesis includes a powerful Service Container that handles dependency injection automatically. The container resolves class dependencies and injects them into constructors and methods.

---

## Auto-Injection

### Constructor Injection

```php
class UserController extends Controller {
    protected $userService;
    
    public function __construct(UserService $userService) {
        $this->userService = $userService;
    }
    
    public function index() {
        return $this->userService->getAllUsers();
    }
}
```

### Method Injection

```php
public function store(Request $request, UserRepository $repo) {
    $data = $request->validate([...]);
    return $repo->create($data);
}
```

---

## Service Container

### Binding Services

```php
// In bootstrap or service provider
$container = \\Nemesis\\Core\\Container::getInstance();

// Bind interface to implementation
$container->bind(UserRepositoryInterface::class, UserRepository::class);

// Bind with closure
$container->bind('mailer', function($container) {
    return new Mailer(config('mail'));
});
```

### Singletons

```php
// Register singleton
$container->singleton(Database::class);

// Always returns same instance
$db1 = $container->make(Database::class);
$db2 = $container->make(Database::class);
// $db1 === $db2
```

---

## Resolving Dependencies

### Manual Resolution

```php
$container = \\Nemesis\\Core\\Container::getInstance();

// Resolve class
$service = $container->make(UserService::class);

// Resolve with parameters
$service = $container->make(UserService::class, ['param' => 'value']);
```

---

## Best Practices

1. **Type-hint dependencies** - Use type hints for auto-injection
2. **Use interfaces** - Depend on abstractions, not concretions
3. **Register singletons** - For shared state (DB, Cache, etc.)
4. **Avoid service locator** - Prefer injection over manual resolution
