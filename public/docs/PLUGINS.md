# Plugin System Documentation

## Overview

The Nemesis Plugin System allows you to extend the **framework itself** (not just the application). Plugins can add core functionality, modify framework behavior, and integrate deeply with the framework lifecycle.

## Plugin vs Module

| Feature | Module | Plugin |
|---------|--------|--------|
| **Purpose** | Extend application | Extend framework |
| **Scope** | Business logic | Core behavior |
| **Load Time** | After framework boot | During framework boot |
| **Example** | Blog, Shop | Auth2FA, Cache, ORM |
| **Dependency** | Application | Framework |

---

## Creating a Plugin

### Using CLI

```bash
php nemesis plugin:create Auth2FA
```

This generates:

```
plugins/Auth2FA/
├── plugin.json          # Manifest (required)
├── bootstrap.php        # Entry point (required)
├── README.md
├── src/                 # Plugin source code
├── config/              # Plugin configuration
└── migrations/          # Database migrations
```

---

## Plugin Manifest (plugin.json)

Every plugin requires a `plugin.json` manifest file:

```json
{
  "name": "auth2fa",
  "version": "1.0.0",
  "description": "Two-factor authentication plugin",
  "entry": "bootstrap.php",
  "requires": {
    "php": ">=8.0",
    "nemesis": "^1.0"
  },
  "autoload": {
    "psr-4": {
      "Nemesis\\Plugins\\Auth2FA\\": "src/"
    }
  },
  "permissions": [
    "routes",
    "middleware",
    "events",
    "db"
  ]
}
```

### Manifest Fields

- **name** (required) - Plugin identifier (lowercase)
- **version** (required) - Semantic version (e.g., 1.0.0)
- **description** - Short description
- **entry** (required) - Bootstrap file path
- **requires** - PHP/framework version requirements
- **autoload** - PSR-4 autoloading configuration
- **permissions** - Required permissions

### Available Permissions

- `routes` - Register routes
- `middleware` - Register middleware
- `events` - Hook into events
- `filesystem` - File system access
- `db` - Database access
- `network` - External HTTP requests

---

## Bootstrap File

The bootstrap file is the plugin's entry point:

```php
<?php
use Nemesis\Core\Plugin;

Plugin::register('auth2fa', function($plugin) {
    // Register routes
    $plugin->route('auth', function() {
        global $router;
        $router->add('POST', '/auth/2fa/verify', [Auth2FAController::class, 'verify']);
    });
    
    // Register middleware
    $plugin->middleware(Require2FAMiddleware::class);
    
    // Register hooks
    Plugin::hook('user.login', function($user) {
        // Send 2FA code
    });
    
    // Register commands
    $plugin->command(Setup2FACommand::class);
});
```

---

## Plugin Management

### List Plugins

```bash
php nemesis plugin:list
```

Output:
```
Nemesis Plugins:

  testplugin v1.0.0 - ✓ Active
    TestPlugin plugin for Nemesis framework
    
  auth2fa v1.2.0 - ✗ Disabled
    Two-factor authentication plugin
```

### Enable Plugin

```bash
php nemesis plugin:enable auth2fa
```

### Disable Plugin

```bash
php nemesis plugin:disable auth2fa
```

---

## Hook System

Plugins can hook into framework lifecycle events:

### Registering Hooks

```php
Plugin::hook('app.boot', function() {
    // Runs when application boots
});

Plugin::hook('user.login', function($user) {
    // Runs when user logs in
});

Plugin::hook('plugin.loaded', function($pluginName) {
    // Runs when a plugin is loaded
});
```

### Firing Hooks

```php
// In your application code
Plugin::fire('user.login', $user);
Plugin::fire('order.created', $order);
```

### Available Hooks

- `app.boot` - Application bootstrapped
- `plugin.loaded` - Plugin loaded
- `router.before` - Before routing
- `response.after` - After response
- Custom hooks - Define your own!

---

## Plugin Routes

Register routes within your plugin:

```php
Plugin::register('myplugin', function($plugin) {
    $plugin->route('api/v1', function() {
        global $router;
        
        $router->add('GET', '/api/v1/users', function() {
            header('Content-Type: application/json');
            echo json_encode(['users' => User::all()]);
        });
    });
});
```

---

## Plugin Middleware

Create middleware in `src/`:

```php
<?php
namespace Nemesis\Plugins\Auth2FA;

class Require2FAMiddleware {
    public function handle($request) {
        if (!$request->user()->has2FA()) {
            return ApiResponse::forbidden('2FA required');
        }
        return $request;
    }
}
```

Register in bootstrap:

```php
$plugin->middleware(Require2FAMiddleware::class);
```

---

## Sandboxing & Security

### Permission Enforcement

Plugins can only perform actions they have permission for:

```php
// In plugin.json
"permissions": ["routes", "middleware"]
```

If a plugin tries to access the database without `db` permission:

```php
// This will throw an exception
Database::query('SELECT * FROM users');
// Exception: Plugin 'myplugin' lacks permission: db
```

### Path Validation

Plugins cannot access files outside the project directory:

```php
// This will be blocked
file_get_contents('/etc/passwd');
// Exception: Plugin attempted to access path outside project
```

---

## Example: Auth2FA Plugin

### Step 1: Create Plugin

```bash
php nemesis plugin:create Auth2FA
```

### Step 2: Update Manifest

Edit `plugins/Auth2FA/plugin.json`:

```json
{
  "name": "auth2fa",
  "version": "1.0.0",
  "description": "Two-factor authentication plugin",
  "entry": "bootstrap.php",
  "requires": {
    "php": ">=8.0",
    "nemesis": "^1.0"
  },
  "autoload": {
    "psr-4": {
      "Nemesis\\Plugins\\Auth2FA\\": "src/"
    }
  },
  "permissions": ["routes", "middleware", "db", "events"]
}
```

### Step 3: Create Controller

`plugins/Auth2FA/src/Auth2FAController.php`:

```php
<?php
namespace Nemesis\Plugins\Auth2FA;

use Nemesis\Core\Controller;

class Auth2FAController extends Controller {
    public function verify() {
        $request = new Request();
        $code = $request->input('code');
        
        if ($this->validate2FACode($code)) {
            return ApiResponse::success(['verified' => true]);
        }
        
        return ApiResponse::error('Invalid 2FA code', 401);
    }
}
```

### Step 4: Implement Bootstrap

`plugins/Auth2FA/bootstrap.php`:

```php
<?php
use Nemesis\Core\Plugin;
use Nemesis\Plugins\Auth2FA\Auth2FAController;
use Nemesis\Plugins\Auth2FA\Require2FAMiddleware;

Plugin::register('auth2fa', function($plugin) {
    // Register routes
    $plugin->route('auth', function() {
        global $router;
        $router->add('POST', '/auth/2fa/verify', [Auth2FAController::class, 'verify']);
        $router->add('POST', '/auth/2fa/send', [Auth2FAController::class, 'send']);
    });
    
    // Register middleware
    $plugin->middleware(Require2FAMiddleware::class);
    
    // Hook into user login
    Plugin::hook('user.login', function($user) {
        // Send 2FA code via SMS/email
        $code = generate2FACode();
        sendSMS($user->phone, "Your code: {$code}");
    });
});
```

### Step 5: Enable Plugin

```bash
php nemesis plugin:enable auth2fa
```

---

## Best Practices

1. **Use semantic versioning** - Follow semver (1.0.0, 1.1.0, 2.0.0)
2. **Declare all permissions** - Only request what you need
3. **Document your plugin** - Add comprehensive README.md
4. **Test thoroughly** - Ensure compatibility
5. **Handle errors gracefully** - Don't crash the framework
6. **Keep it focused** - One plugin, one purpose

---

## Benefits

✅ **Framework extension** - Modify core behavior  
✅ **Sandboxed execution** - Permission-based security  
✅ **Isolated autoloading** - No dependency conflicts  
✅ **Hook system** - Deep framework integration  
✅ **CLI management** - Easy enable/disable  
✅ **Marketplace-ready** - Built for distribution

---

## Future Features

- **Plugin marketplace** - `plugins.nemesis.dev`
- **Remote installation** - `php nemesis plugin:install nemesis/auth2fa`
- **Dependency resolution** - Automatic plugin dependencies
- **Version management** - `php nemesis plugin:update`
- **Per-plugin vendor** - Isolated composer dependencies
