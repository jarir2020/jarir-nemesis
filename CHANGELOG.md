# Nemesis Framework — Changelog

## [7.1.1] — 2026-08-30

Comprehensive gap-fix release. Closes all 12 known gaps identified during
the framework audit. Recommended update for everyone on 7.1.0.

### ⚠️ Breaking Changes

| Area | Before 7.1.1 | 7.1.1 |
|---|---|---|
| `Crypt::decrypt()` | Accepted legacy `base64(ct::iv)` payloads | Only accepts the new `v2:base64(...)` AEAD envelope. Legacy payloads now throw. |
| Built-in middleware namespace | `App\Http\Middleware\*` | `Nemesis\Http\Middleware\*` |
| `Kernel::$middleware` global list | Included `StartSession` + `VerifyCsrfToken` | Only `CheckForMaintenanceMode`. Session/CSRF live in the `web` group; use the `session` / `csrf` aliases outside it. |

> **Action required for the Crypt breaking change:** re-encrypt any persisted
> data using the new `Crypt::encrypt()` output before upgrading.

### Bug Fixes

| # | Subsystem | Fix |
|---|---|---|
| 1 | `Nemesis\Http\Session` | Added `all()`, `flash()`, `getFlash()`, `getOldInput()`, `flashOldInput()`, `pull()`, `reflash()`, `keep()`. The `Gate::checkAcl()` and `flash()`/`old()` helpers no longer fatal. |
| 2 | `Nemesis\Security\Crypt` | Rewritten as AEAD: `AES-256-GCM` for confidentiality + integrity, plus an explicit `HMAC-SHA256` envelope. New subkeys derived via `hash_hkdf`. Throws on any tampering. |
| 3 | `Nemesis\Core\PluginSandbox` | `setupSandbox()` now installs `open_basedir = base_path()` and restores it on `teardownSandbox()`. `checkFileAccess()` rejects null-byte injection, `phar://`-style stream wrappers, and `..`-style escapes with a realpath() check. |
| 4 | `Nemesis\Core\Builder` | `with()` now actually eager-loads. After hydration, each requested relation is loaded in a single batched query (`WHERE IN`) and distributed back into the parent models' relation cache. Replaces the previous N+1. Supports `hasOne`, `hasMany`, `belongsTo`, `belongsToMany`. |
| 5 | Scaffolder `make:model` | The `model.stub` now extends `Nemesis\Core\Model` (AR) instead of `Nemesis\Core\Fluent` (raw QB). Generated models are immediately usable with `find`, `create`, `save`, `where`, etc. |
| 6 | `Nemesis\Http\Session` | Optional `Session::boot(SessionConfig)` accepts the typed DTO and applies the cookie name + lifetime before `session_start()`. |
| 7 | `index.php` (root) | Now also loads `routes/web.php` and `routes/api.php` (when present), matching the behaviour of `public/index.php`. |
| 8 | Built-in middleware | Moved from `app/Http/Middleware/*` to `src/Http/Middleware/*` (namespace `Nemesis\Http\Middleware`). `app/Http/Middleware/.gitkeep` is kept for app-level overrides. |
| 9 | Scaffolder stubs | `migration.stub` now declares `namespace` + `strict_types`, uses `Schema::create()` and `Schema::dropIfExists()`. All 27 stubs audited. |
| 10 | Placeholder dirs | `src/Interceptors/`, `src/Serializer/`, `src/Telemetry/` — `.gitkeep` files now contain explanatory comments about the planned future content. |
| 11 | `app/Http\Kernel` | Removed `StartSession` and `VerifyCsrfToken` from the global middleware list (they're already in the `web` group). |
| 12 | CLI architecture | Added `Nemesis\Console\CommandRegistry` — a first-class in-memory command registry. `bin/nemesis` consults it before falling back to the legacy `CommandBus` / plugin paths. New `php nemesis list` and `php nemesis help <cmd>` commands. |

### New Public API

- `Nemesis\Http\Session::all(): array`
- `Nemesis\Http\Session::flash(string, $value): void`
- `Nemesis\Http\Session::getFlash(string, $default = null): mixed`
- `Nemesis\Http\Session::flashOldInput(array): void`
- `Nemesis\Http\Session::getOldInput(string, $default = null): mixed`
- `Nemesis\Http\Session::pull(string, $default = null): mixed`
- `Nemesis\Http\Session::reflash(): void`
- `Nemesis\Http\Session::keep(array): void`
- `Nemesis\Http\Session::boot(?SessionConfig): void`
- `Nemesis\Core\Model::setRelation(string, mixed): static`
- `Nemesis\Core\Model::getRelations(): array`
- `Nemesis\Console\CommandRegistry` (singleton with `register()`, `has()`, `run()`, `list()`, `help()`)
- `Nemesis\Security\Crypt::VERSION` (constant `'v2'`)

### New Tests

- `tests/SessionFlashTest.php` — covers all new `Session` methods
- `tests/CryptAeadTest.php` — round-trip, unicode, nondeterminism, tamper detection, legacy rejection, wrong-key failure
- `tests/EagerLoadTest.php` — relation helpers, `with()` fluent API
- `tests/PluginSandboxIsolationTest.php` — path validation, stream-wrapper rejection, `open_basedir` install/restore

### Upgrade from 7.1.0

```bash
composer update jarir/nemesis-framework
```

1. Update any code that referenced `App\Http\Middleware\*` to use `Nemesis\Http\Middleware\*`.
2. Re-encrypt any data stored with the old `Crypt::encrypt()` format.
3. If you relied on session/CSRF being globally registered on every request, attach the `web` middleware group to those routes (or use the `session` / `csrf` aliases directly).

---

## [7.1.0] — 2026-07-06

### What's New

- Added the IP allow/block helper with exact IP, CIDR, and wildcard support.
- Added `php nemesis ip:list`, `ip:allow`, `ip:block`, `ip:unallow`, `ip:unblock`, and `ip:reset`.
- Added the `ip` middleware alias so routes can enforce the policy at runtime.
- Documented the IP access workflow in the CLI, middleware, and security guides.

### Notes

- The default policy remains allow-all until you add allow rules or explicit blocks.
- Block rules always win over allow rules.
- The feature uses `config/ip.php` as the default rules source.

## [7.0.3] — 2026-07-06

### What's New

- Added an isolated `examples/` gallery with 22 ready-to-use MVC, API, plugin, extension, and module skeletons.
- Added `php nemesis examples:list` so the gallery can be browsed from the CLI without touching normal app code.
- Updated the top-level docs to point learners and fast coders at the new starter packs.

### Notes

- The examples are optional and copy-only.
- They are not autoloaded, registered, or injected into a fresh app automatically.
- Existing application workflows remain unchanged.

## [5.1.1] — 2026-04-06

### What's New

#### SQLite as Default Database
Fresh installs now work out of the box with **zero database configuration**. No MySQL server required to get started.

```env
DB_DRIVER=sqlite
DB_DATABASE=database/nemesis.sqlite
```

MySQL and PostgreSQL are still fully supported — just uncomment the relevant block in `.env`.

---

#### Zero-Config Installation
Running `composer create-project jarir/nemesis-framework myapp` now automatically:
- Copies `.env.example` → `.env`
- Generates a secure `APP_KEY`
- Prints a ready message

#### SQLite-Aware Migrations
`MigrationManager` now generates the correct DDL per driver:
- **SQLite** → `INTEGER PRIMARY KEY AUTOINCREMENT`
- **MySQL** → `INT AUTO_INCREMENT PRIMARY KEY ENGINE=INNODB`

#### Driver-Aware `env:doctor`
The `php nemesis env:doctor` health check tests the actual configured driver (SQLite / MySQL / PostgreSQL) instead of always attempting a MySQL connection.

#### Auto-Create SQLite Directory
`Database::connectSqlite()` now creates the `database/` directory automatically if it does not exist.

---

### Bug Fixes

| Location | Fix |
|---|---|
| `bin/nemesis` `updateEnv()` | Fixed `Undefined variable $projectRoot` warning |
| `bin/nemesis` `env:doctor` | Fixed config key `user` → `username` (caused undefined key warning) |
| `DatabaseConfig::required()` | SQLite no longer requires `DB_HOST`, `DB_NAME`, `DB_USER` in bootstrap validation |
| `composer.json` post-install | Fixed `\n` printing literally — replaced with `PHP_EOL` |

---

### Security

- `aws/aws-sdk-php` updated to `3.376.3` — resolves **HIGH** severity advisory [GHSA-27qh-8cxx-2cr5](https://github.com/advisories/GHSA-27qh-8cxx-2cr5) *(CloudFront Policy Document Injection)*

---

### New Files

| File | Purpose |
|---|---|
| `.env.example` | Ships with the package for the first time; `create-project` copies it automatically |
| `.gitignore` | Now excludes `*.sqlite`, `*.sqlite-shm`, `*.sqlite-wal` |

---

### Upgrade from 5.0.0

```bash
composer update jarir/nemesis-framework
```

Update your `.env` to use SQLite (optional — MySQL still works):

```env
DB_DRIVER=sqlite
DB_DATABASE=database/nemesis.sqlite
```

---

## [5.0.0] — 2026-04-04

Complete ground-up rewrite across 15 phases. **769/769 tests passing.**

### Phases Completed

| Phase | Feature |
|---|---|
| 1 | Foundation — PSR-11 contracts, exception hierarchy, strict types |
| 2 | ORM — Collection returns, Schema Builder, multi-driver DB |
| 3 | Middleware — typed pipeline, immutable Request, Response factories |
| 4 | Routing — model binding, named routes, subdomain, health check |
| 5 | Error Handling — exception-aware ErrorHandler, 8 error views |
| 6 | Container PSR-11 — contextual/tagged/deferred bindings |
| 7 | Events, Plugin System, CLI — typed events, manifest v2, Scaffolder, CommandBus |
| 8 | Testing — HTTP client, DB assertions, EventFake/QueueFake/MailFake |
| 9 | Config, Queue, i18n — typed DTOs, failed jobs, retry, PasswordStrength |
| 10 | Asset Pipeline — Vite + Webpack manifests, HMR, `frontend:install` CLI |
| 11 | Template Engine — Blade-compatible compiler, directives, layout/sections |
| 12 | Core CMS — Hook/Filter system, ContentTypes, Menus, MetaStore, Revisions |
| 13 | Admin Panel + Media Library — RBAC admin, dashboard widgets, ImageProcessor |
| 14 | Notifications + Search — 6 channels, Notifiable trait, MeiliSearch driver |
| 15 | E-Commerce — Payment gateways (Stripe/PayPal), Catalog, Cart, Orders, Inventory |

### Install

```bash
composer create-project jarir/nemesis-framework myapp
```

---

## [4.0.0] — 2026-03-01

- Plugin System with sandboxed execution
- DebugBar, CloudStorage, Swagger, IdeHelper, Audit plugins
- Advanced routing with CSRF protection
- Robust validation engine
- Premium Documentation Viewer

---

## [3.0.0] — 2025-12-01

- Initial public release
- MVC structure with DI container
- Basic ORM and routing
- CLI scaffolding tools
