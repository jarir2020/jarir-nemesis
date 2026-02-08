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
- `make:model {Name}`: Create a new model class.
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
- `db:dump`: Export the entire database to a SQL file.
- `db:restore {file.sql}`: Import a database from a SQL file.

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
