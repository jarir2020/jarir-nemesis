# Encryption Documentation

Nemesis provides a simple and secure encryption service for protecting sensitive data.

---

## Configuration

The encryption service uses the `AES-256-CBC` cipher and requires a 32-character key.

You can set the key in a bootstrap file or ServiceProvider:

```php
use Nemesis\Security\Crypt;

Crypt::setKey('your-32-character-secure-key-here');
```

---

## Basic Usage

### Encrypting a Value

```php
use Nemesis\Security\Crypt;

$encrypted = Crypt::encrypt('sensitive information');
```

The `encrypt` method returns a base64 encoded string containing both the encrypted data and the Initialization Vector (IV).

### Decrypting a Value

```php
$decrypted = Crypt::decrypt($encrypted);

if ($decrypted === false) {
    // Decryption failed (invalid key or tampered payload)
}
```

---

## Best Practices

1.  **Keep Keys Secret**: Never hardcode your encryption keys in the codebase. Use environment variables.
2.  **Key Rotation**: If you change your encryption key, you must re-encrypt all previously encrypted data with the new key, as the old data will be indecipherable.
3.  **Use for Sensitive Data**: Only encrypt data that truly needs protection (e.g., API keys, external service credentials). Do not use this for passwords; use `Helpers::passwordHash` instead.
