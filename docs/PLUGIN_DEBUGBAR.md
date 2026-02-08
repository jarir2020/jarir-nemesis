# DebugBar Plugin for Nemesis

A powerful debug toolbar for the Nemesis Framework, providing insights into request execution, database queries, memory usage, and more.

## Installation

This plugin is installed by default in the Nemesis project.

To enable it:

```bash
php nemesis plugin:enable DebugBar
```

## Features

- **Time Collector**: detailed timeline of request execution.
- **Memory Collector**: peak memory usage tracking.
- **Query Collector**: logs all SQL queries executed via `Nemesis\Core\Database`.
- **Request Collector**: inspection of request headers, session data, and cookies.

## Usage

Once enabled, the DebugBar is automatically injected into any HTML response with a `</body>` tag.

### Manually Adding Messages

You can log messages to the "Messages" tab:

```php
use Nemesis\Plugins\DebugBar\DebugBar;

DebugBar::info('Hello World');
DebugBar::error('Something went wrong');
DebugBar::warning('Watch out!');
DebugBar::addMessage('My Label', 'Custom message');
```

## Configuration

The plugin hook configuration is handled in `bootstrap.php`.
