# Audit Plugin for Nemesis

Enterprise-grade audit logging for your Nemesis application. Tracks changes to your Eloquent models.

## Features

- **Event Listeners**: Automatically hooks into `created`, `updated`, and `deleted` events.
- **Dirty Checking**: Records only changed attributes (`old_values` vs `new_values`).
- **Context Awareness**: Captures user ID, IP address, User Agent, and URL.
- **Audit Viewer**: Built-in UI to view audit logs.

## Installation

```bash
php nemesis plugin:enable Audit
php nemesis migrate:run
```

## Usage

### Enabling on Models

Add the `AuditTrait` to any model you want to track:

```php
use Nemesis\Core\Model;
use Nemesis\Plugins\Audit\Traits\AuditTrait;

class Post extends Model {
    use AuditTrait;
    
    // ...
}
```

### Viewing Logs

Visit `/audit` in your browser to see a list of recent changes.
Click on an individual log to compare old and new values.

## Configuration

The `audits` table migration is automatically handled by the plugin.
