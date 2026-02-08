# Security Documentation

## Overview

Nemesis provides multiple security features to protect your application from common vulnerabilities.

---

## Encryption

### Using Crypt Service

```php
use Nemesis\Security\Crypt;

// Encrypt data
$encrypted = Crypt::encrypt('sensitive-data');

// Decrypt data
$decrypted = Crypt::decrypt($encrypted);

// Encrypt arrays/objects
$encrypted = Crypt::encrypt(['key' => 'value']);
$data = Crypt::decrypt($encrypted); // Returns array
```

---

## CSRF Protection

### Enable CSRF

```php
// Apply to routes
$router->add('POST', '/form', [FormController::class, 'submit'], ['csrf']);
```

### In Forms

```php
<form method="POST" action="/submit">
    <input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">
    <!-- form fields -->
</form>
```

### In AJAX

```javascript
fetch('/api/data', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: JSON.stringify(data)
});
```

---

## XSS Prevention

### Escape Output

```php
// In views
<?= e($user->name) ?>

// In PHP
echo htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
```

### Content Security Policy

```php
header("Content-Security-Policy: default-src 'self'");
```

---

## SQL Injection Prevention

### Use Parameter Binding

```php
// Good (parameterized)
DB::table('users')->where('email', $email)->first();

// Bad (string concatenation)
DB::raw("SELECT * FROM users WHERE email = '$email'"); // NEVER DO THIS
```

---

## Password Security

### Hashing

```php
// Hash password
$hashed = password_hash($password, PASSWORD_BCRYPT);

// Verify
if (password_verify($input, $hashed)) {
    // Correct password
}
```

### Password Requirements

```php
$rules = [
    'password' => 'required|min:8|regex:/[A-Z]/|regex:/[0-9]/'
];
```

---

## Rate Limiting

### Throttle Middleware

```php
$router->add('POST', '/api/data', [ApiController::class, 'store'], ['throttle:60,1']);
// 60 requests per 1 minute
```

---

## Security Headers

```php
// In middleware or bootstrap
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000');
```

---

## Best Practices

1. **Always escape output** - Prevent XSS
2. **Use CSRF tokens** - Protect forms
3. **Parameterize queries** - Prevent SQL injection
4. **Hash passwords** - Use bcrypt/argon2
5. **Use HTTPS** - Encrypt data in transit
6. **Validate input** - Never trust user data
7. **Keep dependencies updated** - Patch vulnerabilities
8. **Use security headers** - Defense in depth
