# Phase 6 — Container PSR-11 ✓

**Completed:** 2026-04-02

## What Was Done

### `src/Core/Container.php`
- Added `implements \Nemesis\Contracts\ContainerInterface`
- Added **`has(string $id): bool`** — checks registered bindings, cached singletons, and auto-wireable classes via `class_exists()`
- Added **`get(string $id): mixed`** — PSR-11 entry point, delegates to `make()`
- Updated `make()` signature: `make(string $abstract, array $parameters = []): mixed`
  - Named **primitive overrides** now supported — pass `['paramName' => value]` to override auto-wired primitives
  - Singleton cache is bypassed when primitive overrides are supplied (correct behaviour — overrides imply a distinct instance)
- Updated `bind()` / `singleton()` to typed signatures: `callable|string|null`
- Internal `resolve()` and `resolveDependencies()` updated to thread `$primitives` through

### `tests/unit/Phase6Test.php`
19 tests covering:
- `ContainerInterface` compliance
- `has()` — bound, cached singleton, auto-wireable, non-existent
- `get()` — closure binding, auto-wire
- `bind()` — closure, class string, transient (new instance each call)
- `singleton()` — class, closure (same instance)
- Auto-wiring — simple class, class with typed dependency, default param value
- `make($abstract, $parameters)` — named primitive override
- `getInstance()` / `setInstance()` — global singleton accessor
- Interface → concrete binding resolution

## Test Results
```
Total: 19  Passed: 19  Failed: 0
```
(Phase 1 suite: 30/30 still passing — Container changes are backward-compatible)

## Next Phase
**Phase 2 — ORM & Database Layer** (heaviest phase — needs full context window)
