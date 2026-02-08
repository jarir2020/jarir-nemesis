# Task Scheduling Documentation

Nemesis allow you to fluently and expressively define your command schedule within the framework itself.

---

## Defining Schedules

Schedules are defined in `app/Console/Kernel.php` (you may need to create this file if it doesn't exist).

### Example Kernel

```php
namespace App\Console;

use Nemesis\Console\Schedule;

class Kernel {
    public function schedule(Schedule $schedule) {
        // Run a closure every minute
        $schedule->call(function () {
            // Your logic here
        })->everyMinute();

        // Run a CLI command every hour
        $schedule->command('cache:clear')->hourly();
    }
}
```

---

## Scheduling Options

The following scheduling frequencies are available:
- `->everyMinute()`
- `->everyFiveMinutes()`
- `->hourly()`
- `->daily()`

---

## Running the Scheduler

To execute the scheduled tasks, you should add a single Cron entry to your server that runs the `schedule:run` command every minute:

```bash
* * * * * cd /path-to-your-project && php nemesis schedule:run >> /dev/null 2>&1
```

For local testing:
```bash
php nemesis schedule:run
```
