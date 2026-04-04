# Phase 4 — Routing Enhancements ✓

**Completed:** 2026-04-02

## What Was Done

### `src/Router/Router.php`
- Added `static getInstance()` / `setInstance()` — singleton accessor used by the `route()` global helper
- Added `generate(string $name, array $params = []): string` — real named-route URL generation, replaces the `'#'` stub that existed since Phase 1. Handles `{param}`, `{param?}`, and strips unresolved optional segments.
- Added `bind(string $param, callable $resolver): void` — per-router model binding; applied during dispatch, converts raw param strings into objects/models before the controller is called
- Added **subdomain / domain routing** — `group(['domain' => 'api.example.com'], ...)` stores the domain on each route; dispatch checks `HTTP_HOST` against a regex built from the domain pattern (supports `{tenant}` wildcards)
- Added `scanAttributes(string $controllerClass): void` — reads `#[Route]` attributes on all public methods and registers the routes automatically
- Cleaned up all method return types and parameter types (PHP 8.2 strict)
- Dispatch: `callAction()` extracted, optional-param regex `{param?}` added, model binders applied after match

### `src/Router/RouteModelBinder.php` _(new)_
- Static registry: `bind()`, `resolve()`, `resolveAll()`, `has()`, `flush()`, `all()`
- Useful for registering binders in ServiceProviders before the Router is instantiated

### `src/Attributes/Route.php` _(new)_
- PHP 8 `#[Attribute]` — `TARGET_METHOD | IS_REPEATABLE`
- Constructor properties: `$method`, `$uri`, `$name`, `$middleware`
- Enables declarative route registration directly on controller methods

### `src/Http/HealthCheck.php` _(new)_
- `GET /_health` endpoint — returns JSON, 200 OK / 503 Degraded
- Checks: **database** (PDO ping), **cache** (Redis connect or file-dir writable), **disk** (free % — fails below 10%), **PHP** (version + memory)

### `routes/route.php`
- Added `$router->get('/_health', [HealthCheck::class, 'handle'])->name('health');`

### `route()` global helper (`src/Helpers/Helpers.php`)
- Was a stub returning `'#'`. Now works fully because `Router::getInstance()` exists and `generate()` is implemented. **No change needed to Helpers.php.**

## Test Results
```
Phase 4:  35/35 passed
Phase 6:  19/19 passed  (regression check)
Phase 1:  30/30 passed  (regression check — pre-existing Helper/Flash failure unchanged)
Total:   122 run, 121 passed, 1 pre-existing failure
```

## Next Phase
**Phase 3 — Middleware & Request/Response**
