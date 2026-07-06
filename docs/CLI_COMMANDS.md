# CLI Commands Reference

## Overview

Nemesis provides a powerful command-line interface (CLI) called `nemesis`. It helps with scaffolding, database management, and running system tasks.

---

## General

| Command | Description |
|---------|-------------|
| `php nemesis list` | List all available commands |
| `php nemesis help [command]` | Display help for a command |
| `php nemesis serve` | Start the development server |
| `php nemesis docs:sync [--dry-run] [--json] [--pretty] [--brief]` | Mirror `docs/` and `public/docs/` by timestamp |
| `php nemesis data:sync [--bangladesh-source=...] [--json] [--pretty] [--brief]` | Scaffold `public/data/` packs and import Bangladesh locations |
| `php nemesis ip:list [--json] [--pretty] [--brief]` | Show the current IP allow/block policy |
| `php nemesis ip:allow <ip|cidr|pattern>` | Add an IP rule to the allow list |
| `php nemesis ip:block <ip|cidr|pattern>` | Add an IP rule to the block list |
| `php nemesis ip:unallow <ip|cidr|pattern>` | Remove an IP rule from the allow list |
| `php nemesis ip:unblock <ip|cidr|pattern>` | Remove an IP rule from the block list |
| `php nemesis ip:reset` | Restore the default allow-all policy |
| `php nemesis env:doctor` | Check environment health |
| `php nemesis key:generate` | Generate application key |

---

## Maintenance

### `vendor:compress`

Compress the vendor tree by removing only classes proven unused by the application graph.

```bash
php nemesis vendor:compress [--dry-run] [--report[=path]] [--json] [--keep=<package>] [--exclude=<path>] [--archive=<path>] [--restore=<path>]
```

**Flags**
- `--dry-run`: Analyze without changing files.
- `--report[=path]`: Write a human-readable report to disk.
- `--json`: Emit machine-readable JSON output.
- `--keep=<package>`: Preserve a package or namespace root.
- `--exclude=<path>`: Exclude a path from removal and restore operations.
- `--archive=<path>`: Store removable files in an archive before deletion.
- `--restore=<path>`: Restore from a prior archive or manifest.

**Examples**

Dry run:

```bash
php nemesis vendor:compress --dry-run --json
```

Restore:

```bash
php nemesis vendor:compress --restore=.nemesis/vendor-compress/2026-07-05T103000+06:00.json
```

**JSON payload example**

```json
{
  "command": "vendor:compress",
  "timestamp": "2026-07-05T10:30:00+06:00",
  "mode": "dry-run",
  "scope": {
    "root": ".",
    "packages": ["vendor/jarir-ahmed/cache", "vendor/jarir-ahmed/notification-system"]
  },
  "flags": {
    "dry_run": true,
    "json": true,
    "report": "storage/reports/vendor-compress.json",
    "keep": ["laravel/framework"],
    "exclude": ["vendor/bin"]
  },
  "summary": {
    "scanned_files": 1824,
    "scanned_classes": 9120,
    "preserved_classes": 8801,
    "removable_classes": 319,
    "removed_files": 0,
    "unresolved_references": 14
  },
  "preserved": [
    {
      "path": "vendor/composer/autoload_psr4.php",
      "package": "composer/composer",
      "type": "bootstrap",
      "reason": "composer metadata and autoload bootstrap"
    }
  ],
  "candidates": [
    {
      "path": "vendor/example/package/src/UnusedHelper.php",
      "package": "example/package",
      "type": "class",
      "reason": "no imports, no classmap references, no runtime bootstrap references"
    }
  ],
  "skipped": [],
  "warnings": [
    "14 references could not be resolved safely and were preserved"
  ],
  "restore": {
    "archive_path": ".nemesis/vendor-compress/2026-07-05T103000+06:00.tar.gz",
    "manifest_path": ".nemesis/vendor-compress/2026-07-05T103000+06:00.json",
    "restore_command": "php nemesis vendor:compress --restore=.nemesis/vendor-compress/2026-07-05T103000+06:00.json"
  }
}
```

---

## Scaffolding (Make)

Create new application classes.

| Command | Description |
|---------|-------------|
| `make:controller {name}` | Create a new controller class |
| `make:model {name} [--connection=name]` | Create a new Eloquent model class and pin it to a named database connection |
| `make:migration {name}` | Create a new migration file |
| `make:middleware {name}` | Create a new middleware class |
| `make:request {name}` | Create a new form request class |
| `make:job {name}` | Create a new job class |
| `make:command {name}` | Create a new console command |
| `make:test {name}` | Create a new test class |
| `make:module {name}` | Create a new application module |
| `make:resource {name}` | Create a full resource (CRUD stack) |

**Options:**
- `--module={name}`: Create inside a module (e.g., `make:controller Blog --module=Blog`)
- `--migration` or `-m`: Create a migration file for the model

---

## Database

Manage your database schema and data.

| Command | Description |
|---------|-------------|
| `migrate:run` | Run pending migrations |
| `migrate:rollback` | Rollback the last batch of migrations |
| `migrate:reset` | Rollback all migrations |
| `migrate:refresh` | Reset and re-run all migrations |
| `migrate:fresh` | Drop all tables and re-run migrations |
| `migrate:status` | Show status of each migration |
| `db:seed` | Seed the database with records |
| `db:list-connections` | List configured database connections |
| `db:dump` | Dump database to SQL file |
| `db:restore` | Restore database from SQL file |

---

## Plugins

Manage framework plugins.

| Command | Description |
|---------|-------------|
| `plugin:list` | List all installed plugins |
| `plugin:create {name}` | Create a new plugin skeleton |
| `plugin:enable {name}` | Enable a plugin |
| `plugin:disable {name}` | Disable a plugin |

---

## System

Run system services.

| Command | Description |
|---------|-------------|
| `queue:work` | Start processing jobs on the queue |
| `queue:listen` | Listen to a given queue |
| `queue:failed` | List all failed queue jobs |
| `queue:retry {id}` | Retry a failed job |
| `schedule:run` | Run the scheduled tasks |
| `websockets:serve` | Start the WebSocket server |

---

## Optimization

Optimize the application for production.

| Command | Description |
|---------|-------------|
| `route:cache` | Create a route cache file for faster registration |
| `route:clear` | Remove the route cache file |
| `config:cache` | Create a cache file for faster configuration loading |
| `config:clear` | Remove the configuration cache file |
| `view:cache` | Compile all view templates |
| `view:clear` | Clear compiled view files |

---

## Utility

| Command | Description |
|---------|-------------|
| `tinker` | Interact with your application via CLI shell |
| `api:probe` | Test all API routes for availability |
| `model:health` | Check models for common issues (e.g., N+1) |
| `examples:list` | Browse the optional starter gallery |

---

## Creating Custom Commands

### Generate Command

```bash
php nemesis make:command SendEmails
```

### Command Structure

```php
<?php
namespace App\Console\Commands;

use Nemesis\Console\Command;

class SendEmails extends Command {
    protected $signature = 'email:send {user}';
    protected $description = 'Send emails to a user';
    
    public function handle() {
        $userId = $this->argument('user');
        // Logic...
        $this->info('Emails sent!');
    }
}
```
