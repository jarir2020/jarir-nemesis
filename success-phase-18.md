# Phase 18 — Task Scheduler ✓

**Completed:** 2026-04-06  
**Tests:** 906 total / 906 passed (54 new in Phase 18)  
**Branch:** main

---

## What Was Built

### Core Files

| File | Purpose |
|---|---|
| `src/Console/ScheduledTask.php` | Fluent frequency + condition builder (own file for PSR-4) |
| `src/Console/Scheduler.php` | Runner — mutex, logging, builder API |
| `app/Console/Kernel.php` | Where users register scheduled tasks |

### ScheduledTask — Frequency API

```php
->everyMinute()          ->everyFiveMinutes()     ->everyTenMinutes()
->everyFifteenMinutes()  ->everyThirtyMinutes()
->hourly()               ->hourlyAt(30)
->daily()                ->dailyAt('08:00')       ->twiceDaily(6, 18)
->weekly()               ->weeklyOn(1, '08:00')
->monthly()              ->monthlyOn(15, '08:00') ->quarterly()  ->yearly()
->weekdays()             ->weekends()
->mondays() .. ->sundays()
->cron('0 8 * * 1-5')    // raw expression, supports: *, */n, n-m, n-m/s, list
```

### Time-window Conditions

```php
->between('08:00', '18:00')       // only run during office hours
->unlessBetween('22:00', '06:00') // skip overnight
->when(fn(\DateTimeInterface $dt) => !isMaintenanceMode())
->skip(fn($dt) => Cache::has('scheduler_paused'))
```

### Scheduler Runner
- **Overlap protection** — file mutex per task name (`/tmp/nemesis_scheduler/{md5}.lock`)
- **Output capture** — stdout captured per task → `storage/logs/scheduler.log`
- **Error isolation** — exceptions in one task don't stop others
- **DateTime injection** — `run(\DateTimeInterface $now)` for testable scheduling

### Job dispatch

```php
$scheduler->job(new SendReportJob())->weeklyOn(1, '08:00');
// Job must implement handle()
```

### CLI Commands

```bash
php nemesis schedule:run    # Run all due tasks (add to cron: * * * * *)
php nemesis schedule:list   # Table of all registered tasks + cron hint
```

### Cron Setup

```bash
* * * * * php /path/to/project/nemesis schedule:run >> /dev/null 2>&1
```

---

## Usage

```php
// app/Console/Kernel.php
public function schedule(Scheduler $scheduler): void
{
    $scheduler->command('cache:clear')
              ->daily()
              ->description('Clear expired cache');

    $scheduler->call(function () {
        (new Mailer())->sendDigest();
    })->weeklyOn(1, '08:00')         // Every Monday at 8am
      ->between('06:00', '10:00')   // Only during morning window
      ->name('weekly-digest');

    $scheduler->job(new GenerateReportJob())
              ->monthlyOn(1, '00:00')
              ->description('Monthly report generation');
}
```
