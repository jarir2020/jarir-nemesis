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
| `php nemesis env:doctor` | Check environment health |
| `php nemesis key:generate` | Generate application key |

---

## Scaffolding (Make)

Create new application classes.

| Command | Description |
|---------|-------------|
| `make:controller {name}` | Create a new controller class |
| `make:model {name}` | Create a new Eloquent model class |
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
