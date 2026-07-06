# Testing Documentation

## Overview

Nemesis includes a PHPUnit-compatible testing suite designed for developer happiness. It supports unit testing, feature testing, and browser automation.

### Testing `vendor:compress`

When you change the vendor compression workflow, run the following modes in sequence:

1. `php nemesis vendor:compress --dry-run`
2. `php nemesis vendor:compress --dry-run --json`
3. `php nemesis vendor:compress --report=storage/reports/vendor-compress.txt`
4. `php nemesis vendor:compress --archive=.nemesis/vendor-compress/test.tar.gz`
5. `php nemesis vendor:compress --restore=.nemesis/vendor-compress/test.json`

Regression checks should confirm:
- bootstrap files remain untouched
- autoloading still works
- ORM, middleware, auth, routing, and database bootstrapping still pass
- restore rehydrates files in the correct order

---

## Running Tests

```bash
# Run all tests
php nemesis test

# Run specific suite
php nemesis test --suite=unit

# Run specific file
php nemesis test tests/Feature/UserTest.php

# Filter tests
php nemesis test --filter=test_login
```

---

## Creating Tests

```bash
php nemesis make:test UserTest
php nemesis make:test ApiTest --unit
```

### Test Structure

```php
<?php
use Nemesis\Testing\TestCase;

class UserTest extends TestCase {
    public function test_basic_arithmetic() {
        $this->assertEquals(2, 1 + 1);
    }
}
```

---

## HTTP Testing

Test your API endpoints and web pages.

### Basic Requests

```php
public function test_home_page() {
    $response = $this->get('/');
    $response->assertStatus(200);
}

public function test_login() {
    $response = $this->post('/login', [
        'email' => 'test@example.com',
        'password' => 'password'
    ]);
    
    $response->assertRedirect('/dashboard');
}
```

### API Assertion

```php
public function test_api_user() {
    $response = $this->json('GET', '/api/user');
    
    $response
        ->assertStatus(200)
        ->assertJson([
            'created' => true,
        ]);
}
```

### Session & Auth

```php
public function test_authenticated_route() {
    $user = User::factory()->create();
    
    $response = $this->actingAs($user)
                     ->get('/dashboard');
                     
    $response->assertStatus(200);
}
```

---

## Database Testing

### Refresh Database

Use `RefreshDatabase` trait to reset database between tests.

```php
use Nemesis\Testing\RefreshDatabase;

class DatabaseTest extends TestCase {
    use RefreshDatabase;
    
    public function test_database_insert() {
        // ...
    }
}
```

### Assertions

```php
$this->assertDatabaseHas('users', [
    'email' => 'test@example.com'
]);

$this->assertDatabaseMissing('users', [
    'email' => 'deleted@example.com'
]);
```

### Factories

Generate dummy data for tests.

```php
// Create single
$user = User::factory()->create();

// Create multiple
$users = User::factory()->count(10)->create();

// Create with overrides
$admin = User::factory()->create([
    'is_admin' => true
]);
```

---

## Mocking

Mock dependencies to isolate tests.

```php
public function test_order_email() {
    Mail::fake();
    
    // Perform order action...
    
    Mail::assertSent(OrderShipped::class);
}

public function test_storage() {
    Storage::fake('avatars');
    
    // Upload file...
    
    Storage::disk('avatars')->assertExists('avatar.jpg');
}
```

---

## Browser Testing (Dusk)

Automated browser testing for JavaScript-heavy apps.

```php
public function test_login_flow() {
    $this->browse(function ($browser) {
        $browser->visit('/login')
                ->type('email', 'test@example.com')
                ->type('password', 'password')
                ->press('Login')
                ->assertPathIs('/dashboard')
                ->assertSee('Welcome Back!');
    });
}
```

---

## Best Practices

1. **Test isolated units** - Verify small pieces of logic
2. **Test integration** - Verify components work together
3. **Use factories** - Generate realistic test data
4. **Reset database** - Ensure clean state for each test
5. **run tests** - Run frequently during development
