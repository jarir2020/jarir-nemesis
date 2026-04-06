# Phase 19 — HTTP Client ✓

**Completed:** 2026-04-06  
**Tests:** 971 total / 971 passed (65 new in Phase 19)  
**Branch:** main

---

## What Was Built

| File | Purpose |
|---|---|
| `src/Http/Client/HttpResponse.php` | Response wrapper + `HttpResponseException` |
| `src/Http/Client/HttpClient.php` | Fluent PendingRequest builder + `HttpPool` |
| `src/Http/Http.php` | Static facade with fake/recording system |

---

## Usage

### Basic requests
```php
use Nemesis\Http\Http;

$response = Http::get('https://api.example.com/users');
$response = Http::post('https://api.example.com/users', ['name' => 'Alice']);
$response = Http::put('https://api.example.com/users/1', ['name' => 'Bob']);
$response = Http::patch('https://api.example.com/users/1', ['email' => 'b@b.com']);
$response = Http::delete('https://api.example.com/users/1');
```

### Fluent builder
```php
$response = Http::withToken('my-jwt-token')
                ->withHeaders(['X-App-ID' => '123'])
                ->timeout(15)
                ->retry(3, 200)   // 3 retries, 200ms delay
                ->acceptJson()
                ->get('https://api.example.com/users', ['page' => 1]);
```

### Response methods
```php
$r->body();          // raw string
$r->json();          // decoded array
$r->json('data.0.id'); // dot-notation access
$r->status();        // int
$r->ok()             // 200 exactly
$r->successful()     // 200-299
$r->clientError()    // 400-499
$r->serverError()    // 500+
$r->failed()         // !successful
$r->notFound()       // 404
$r->unauthorized()   // 401
$r->unprocessable()  // 422
$r->header('X-RateLimit-Remaining')
$r->throw()          // throws HttpResponseException if failed
$r->throwIf(fn($r) => $r->json('error') !== null)
```

### Auth helpers
```php
Http::withToken('bearer-token')->get(url);
Http::withBasicAuth('user', 'pass')->get(url);
Http::withDigestAuth('user', 'pass')->get(url);
```

### Body formats
```php
Http::asJson()->post(url, $data);         // default
Http::asForm()->post(url, $data);         // form-urlencoded
Http::attach('avatar', file_get_contents('pic.jpg'), 'avatar.jpg')->post(url);
Http::withBody($rawXml, 'application/xml')->post(url);
```

### Concurrent pool
```php
$responses = Http::pool(fn($pool) => [
    'users' => $pool->get('https://api.example.com/users'),
    'posts' => $pool->get('https://api.example.com/posts'),
]);
$responses['users']->json(); // ['data' => [...]]
```

### Testing — fake responses
```php
Http::fake([
    'https://api.example.com/*'  => Http::response(['data' => []], 200),
    'https://api.example.com/me' => Http::response(['id' => 1, 'name' => 'Alice'], 200),
    'https://error.example.com'  => Http::response(['error' => 'boom'], 500),
]);

$r = Http::get('https://api.example.com/users');
$r->wasFaked(); // true
$r->json('data'); // []

Http::assertSent(fn($req) => $req['method'] === 'GET' && $req['url'] === 'https://api.example.com/users');
Http::assertSentCount(1);
Http::assertNothingSent(); // would throw — 1 was sent
Http::resetFakes(); // call in tearDown()
```
