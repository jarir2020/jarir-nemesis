# Nemesis Framework — Changelog

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
