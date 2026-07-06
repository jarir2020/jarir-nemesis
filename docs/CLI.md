# Artisan (Nemesis) CLI Documentation

Nemesis comes with a powerful command-line interface called `nemesis`, which provides helpful commands for developing your application.

---

## Usage

All commands are executed via the `php nemesis` prefix:

```bash
php nemesis list
```

---

## Available Commands

### Scaffolding (Make Commands)
- `make:controller {Name}`: Create a new controller class.
- `make:model {Name}`: Create a new model class. Use `--connection=name` to pin a model to a named database connection.
- `make:middleware {Name}`: Create a new middleware class.
- `make:migration {Name}`: Create a new database migration file.
- `make:seeder {Name}`: Create a new database seeder class.
- `make:job {Name}`: Create a new background job class.
- `make:policy {Name}`: Create a new authorization policy.
- `make:module {Name}`: Create a full module structure.
- `make:resource {Name}`: Generate a complete resource stack (Model, Controller, Views, Migration).

### Database Management
- `migrate:run`: Execute all pending migrations.
- `migrate:rollback`: Rollback the last migration batch.
- `db:seed`: Seed the database with initial data.
- `db:list-connections`: List configured database connections.
- `db:dump`: Export the entire database to a SQL file.
- `db:restore {file.sql}`: Import a database from a SQL file.

### Documentation
- `docs:sync`: Mirror `docs/` and `public/docs/` based on file timestamps. Use `--dry-run` to preview, `--json` for tooling, and `--brief` for compact JSON logs.

### Public Data
- `data:sync`: Scaffold `public/data/` packs and import Bangladesh locations. Use `--bangladesh-source=...` to point at a local file or remote JSON URL, and `--json` / `--brief` for tooling output.

### IP Access
- `ip:list`: Inspect the current IP allow/block policy. Use `--json`, `--pretty`, or `--brief` for machine-readable output.
- `ip:allow`, `ip:block`: Add an IP, CIDR, or wildcard pattern to the allow or block list.
- `ip:unallow`, `ip:unblock`: Remove an entry from the allow or block list.
- `ip:reset`: Restore the default allow-all policy.

### Optimization & Cache
- `optimize`: Warm up config and route caches.
- `cache:clear`: Flush all application caches.
- `config:cache`: Cache configuration files for performance.
- `route:cache`: Cache route definitions.

### Development Utilities
- `serve`: Start the built-in development server.
- `tinker`: Open an interactive REPL shell for your app.
- `key:generate`: Generate and set the application security key.
- `env:doctor`: Run a health check on your environment.
- `model:health`: Scan models for potential N+1 or schema issues.

### Maintenance
- `down`: Put the application into maintenance mode.
- `up`: Bring the application out of maintenance mode.
