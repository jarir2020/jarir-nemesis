# Authentication Documentation

Nemesis provides a flexible authentication system supporting Session-based authentication, JWT tokens, and Two-Factor Authentication (TOTP).

---

## Session Authentication

Session-based authentication is the standard for web applications.

### Basic Usage

Use the `Nemesis\Http\Session` class to manage user sessions.

```php
use Nemesis\Http\Session;

// Log in a user
Session::set('user_id', $user->id);

// Check if logged in
if (Session::has('user_id')) {
    $userId = Session::get('user_id');
}

// Log out
Session::remove('user_id');
```

---

## JWT Authentication

For stateless APIs, Nemesis provides a `JWT` helper.

### Configuration
Set your secret key before usage:

```php
use Nemesis\Auth\JWT;

JWT::setSecret('your-very-secure-secret');
```

### Usage

```php
// Generate a token
$payload = ['user_id' => 123];
$token = JWT::encode($payload, 3600); // Expires in 1 hour

// Verify and decode a token
try {
    $data = JWT::decode($token);
    $userId = $data['user_id'];
} catch (\Exception $e) {
    // Token is invalid or expired
}
```

---

## Two-Factor Authentication (TOTP)

Nemesis includes built-in support for Time-based One-Time Passwords (TOTP), compatible with Google Authenticator and Authy.

```php
use Nemesis\Auth\Totp;

// 1. Setup for a user
$totp = new Totp(null, 'My App', 'user@example.com');
$secret = $totp->getSecret(); // Save this secret to the user's database record

// 2. Generate QR Code URI
$uri = $totp->getProvisioningUri(); // Use this to generate a QR code image

// 3. Verify a code
$isValid = $totp->verify($userInputCode);
```

### Auth Microservice Bridge

Nemesis can optionally delegate auth operations to an external microservice package. When the package client is not available, you can still use the bridge with an injected transport callback in tests or local development.

```php
use Nemesis\Auth\MicroserviceBridge;

MicroserviceBridge::configure([
    'base_url' => 'https://auth.example.com',
    'token' => 'service-token',
]);

$result = MicroserviceBridge::authenticate([
    'email' => 'user@example.com',
    'password' => 'secret',
]);
```

```php
MicroserviceBridge::setTransport(function (string $action, array $payload, array $config): array {
    return [
        'action' => $action,
        'payload' => $payload,
        'base_url' => $config['base_url'] ?? '',
    ];
});
```

---

## Password Security

Always hash passwords using the built-in helpers or PHP's `password_hash`.

```php
use Nemesis\Helpers\Helpers;

// Hashing
$hashed = Helpers::passwordHash('secret123');

// Verification
if (Helpers::passwordVerify('secret123', $hashed)) {
    // Password matches
}
```

---

## Account Verification & Reset

Nemesis provides scaffolded logic for `AccountVerification` and `PasswordReset` in the `Nemesis\Auth` namespace. These utilities handle token generation and expiration for email-based flows.
