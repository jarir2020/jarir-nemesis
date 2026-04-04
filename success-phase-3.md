# Phase 3 — Middleware & Request/Response ✓

**Completed:** 2026-04-02

## What Was Done

### `src/Http/Response.php` — Complete rewrite as value object
- Constructor: `__construct(string $content, int $status, array $headers)`
- Static factories: `make()`, `json()`, `text()`, `redirect()`, `download()`, `stream()`, `view()`
- Fluent immutable modifiers (clone, not mutate): `withStatus()`, `withHeader()`, `withContent()`
- Accessors: `getStatus()`, `getContent()`, `getHeaders()`, `isRedirect()`
- `send()` method handles all output modes: standard, redirect, file download, streaming
- `index.php` already calls `$response->send()` — no change needed there

### `src/Http/Request.php` — Immutable attribute bag
- `withAttribute(string $key, mixed $value): static` — returns a clone with attribute set
- `getAttribute(string $key, mixed $default): mixed` — read middleware-attached data
- `getAttributes(): array` — retrieve all attributes
- Use case: auth middleware does `$request = $request->withAttribute('user', $user)` then passes the cloned request to the next layer

### `src/Http/Pipeline.php` — Response-typed pipeline
- `then()` now returns `Response` (not `mixed`)
- `destination()` wraps controller returns: Response as-is, string → `Response::make()`, null → empty Response
- `carry()` likewise coerces each middleware's return through `toResponse()`
- `terminate(mixed $request, Response $response): void` — new Phase 3 method; calls `terminate()` on every middleware object that has it (post-response background work)

### Middleware — all 9 now implement `MiddlewareInterface`

**`app/Http/Middleware/`** (5 files):
| Class | Change |
|---|---|
| `CheckForMaintenanceMode` | Returns `Response::json([], 503)` instead of echo+exit |
| `StartSession` | + `terminate()` to flush session after response |
| `VerifyCsrfToken` | Returns `Response::json([], 419)`, fixed wildcard except matching |
| `ThrottleRequests` | Returns `Response::json([], 429)` with rate-limit headers; attaches headers to pass-through response |
| `TestMiddleware` | Typed, uses `@` suppress for non-critical log writes |

**`src/Middleware/`** (4 files — discovered during Phase 3):
| Class | Change |
|---|---|
| `CorsMiddleware` | OPTIONS returns `Response::make('', 204)` instead of exit |
| `SecurityHeadersMiddleware` | Attaches headers via `$response->withHeader()` instead of global `header()` |
| `ApiVersionMiddleware` | Typed, guarded `define()` with `!defined()` to prevent re-define errors |
| `DebugBar` | Uses `$response->getContent()` / `withContent()` to inject debug bar |

### `app/Http/Kernel.php` — Groups & aliases expanded
- `'web'` group: StartSession + VerifyCsrfToken
- `'api'` group: `throttle:60,1`
- New route aliases: `cors`, `security`, `api.version`, `debugbar`

## Test Results
```
Phase 3:  48/48 passed
Phase 4:  35/35 passed  (regression)
Phase 6:  19/19 passed  (regression)
Phase 1:  30/30 passed  (regression — pre-existing Helper/Flash failure unchanged)
Total:   169 run, 168 passed, 1 pre-existing failure
```

## Next Phase
**Phase 8 — Testing Infrastructure** (next smallest pending)
