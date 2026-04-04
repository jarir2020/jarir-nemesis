# Phase 1 — Foundation & Infrastructure ✅

**Completed:** 2026-04-02
**Tests:** 68 / 68 PASS
**Type:** Additive only — zero breaking changes, app stays fully functional

---

## What Was Built

### composer.json
- Version bumped: `3.0.0` → `4.0.0`
- PHP floor raised: `>=8.0` → `>=8.2`
- New namespace autoloads registered: `Contracts\`, `Exceptions\`, `Attributes\`
- `bin/nemesis` registered as Composer binary

### src/Contracts/ (9 interfaces)
Stable public API layer. Depend on these in plugins and app code — not on internals.

| Interface | Purpose |
|---|---|
| `ContainerInterface` | PSR-11 + `make`, `bind`, `singleton` |
| `MiddlewareInterface` | `handle(Request, callable): Response` |
| `ListenerInterface` | `handle(object $event): void` |
| `ModelInterface` | ORM model stable surface |
| `RepositoryInterface` | Data access contract |
| `CacheInterface` | Cache driver contract |
| `QueueInterface` | Queue driver contract |
| `CommandInterface` | Console command contract |
| `EventInterface` | Marker for typed event classes |

### src/Exceptions/ (12 classes + 2 concern interfaces)
Full exception hierarchy. Phase 5 wires these into ErrorHandler.

```
NemesisException (extends RuntimeException)
└── HttpException (carries $statusCode)
    ├── NotFoundException       (404)
    ├── ForbiddenException      (403)
    ├── UnauthorizedException   (401)
    ├── MethodNotAllowedException (405)
    └── TooManyRequestsException  (429)
DatabaseException
ConfigurationException
PluginException
Concerns\RenderableException (interface)
Concerns\ReportableException (interface)
```

### src/Attributes/ (2 attribute classes)
- `#[InternalApi]` — marks classes/methods as internal, no stability guarantee
- `#[StableApi]` — marks classes/methods as safe to depend on

### src/Helpers/Helpers.php (13 new global functions)
| Function | Description |
|---|---|
| `app($class)` | Resolve from container |
| `config($key, $default)` | Get config value |
| `route($name, $params)` | Named route URL (stub until Phase 4) |
| `abort($code, $message)` | Throw typed HTTP exception |
| `abort_if($cond, $code)` | Conditional abort |
| `abort_unless($cond, $code)` | Inverse conditional abort |
| `now()` | Returns `new DateTime()` |
| `collect($array)` | Wraps in `Collection` |
| `dd(...$vars)` | Dump and die |
| `dump(...$vars)` | Dump only |
| `flash($key, $value)` | Flash message to session |
| `old($key, $default)` | Old form input |
| `class_basename($class)` | Moved from Model.php global scope |

### strict_types audit
- **113 src/ files patched** with `declare(strict_types=1)`
- 23 already had it or were skipped

### Folder Scaffold (35 new directories)
All created with `.gitkeep`. Opt-in — empty until used.

```
app/Traits/          app/Repositories/    app/Entities/
app/DTOs/            app/Transformers/    app/Managers/
app/Handlers/        app/Interfaces/      app/Factory/
app/Language/        app/Filters/         app/Widgets/
app/Libraries/       app/Helpers/
views/errors/        views/demo_templates/
src/Interceptors/    src/Reactor/         src/Scaffolder/
src/Serializer/      src/Tokenizer/       src/Telemetry/
app/Helpers/Flash/        app/Helpers/Filter/       app/Helpers/Paginator/
app/Helpers/Datamapper/   app/Helpers/Annotations/  app/Helpers/Optimizers/
tests/unit/          tests/database/      tests/session/
tests/mocks/         tests/response/      tests/_support/
bin/
```

### bin/nemesis
CLI binary copied to `bin/nemesis` (Symfony convention). Root `nemesis` kept for backwards compat.

---

## Bug Found & Fixed
`abort()` helper was passing `$message` as first arg to `HttpException(int, string)` for non-mapped status codes. Fixed to call `new HttpException($code, $message)` explicitly.

---

## Ready For
**Phase 2 — ORM & Database Layer**
- `Fluent::get()` → returns `Collection` of hydrated Models
- Schema Builder DSL (`Schema::create`, `Blueprint`)
- Multi-driver DB support (MySQL, PostgreSQL, SQLite)
- `SoftDeletes` as a proper Trait
