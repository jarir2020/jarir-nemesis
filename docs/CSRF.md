# CSRF Protection

## Overview

Cross-Site Request Forgery (CSRF) is a malicious exploit where unauthorized commands are transmitted from a user that the web application trusts. Nemesis provides built-in CSRF protection for all requests handled by the application.

CSRF protection is enabled by default via the `\App\Http\Middleware\VerifyCsrfToken` middleware in `app/Http/Kernel.php`.

---

## preventing CSRF Requests

### HTML Forms

Any HTML form pointing to `POST`, `PUT`, `PATCH`, or `DELETE` routes should include a hidden CSRF token field. This token verifies that the authenticated user is the one actually making the request.

```html
<form method="POST" action="/profile">
    <input type="hidden" name="_token" value="<?= csrf_token() ?>">
    <!-- form inputs -->
    <button type="submit">Update</button>
</form>
```

### AJAX Requests

For JavaScript-driven applications, you can add the token to a meta tag in your `head` section:

```html
<head>
    <meta name="csrf-token" content="<?= csrf_token() ?>">
</head>
```

Then, configure your AJAX library (like Axios or Fetch) to send the `X-CSRF-TOKEN` header:

```javascript
// Using Fetch
const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

fetch('/api/user', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token
    },
    body: JSON.stringify({ name: 'John' })
});
```

---

## Excluding URIs

Sometimes you may wish to exclude specific URIs from CSRF protection, such as external webhooks (e.g., Stripe, PayPal).

You can exclude URIs by adding them to the `$except` property in `app/Http/Middleware/VerifyCsrfToken.php`:

```php
<?php

namespace App\Http\Middleware;

class VerifyCsrfToken {
    /**
     * The URIs that should be excluded from CSRF verification.
     */
    protected $except = [
        'webhook/stripe',
        'webhook/paypal',
        'api/*', // Wildcards are supported
    ];
}
```

---

## X-CSRF-TOKEN

In addition to checking for the `_token` parameter in POST data, the `VerifyCsrfToken` middleware will also check for the `X-CSRF-TOKEN` request header.

```php
// VerifyCsrfToken.php logic
$token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
```
