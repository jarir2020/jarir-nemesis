<?php
declare(strict_types=1);

// Nemesis 6.2.1 | Phase 18 — Console Kernel (task scheduler) | 2026-04-06

namespace App\Console;

use Nemesis\Console\Scheduler;

/**
 * Console Kernel — register all scheduled tasks here.
 *
 * The Scheduler runs every minute via cron:
 *   * * * * * php /path/to/project/nemesis schedule:run >> /dev/null 2>&1
 *
 * Available frequency methods:
 *   ->everyMinute()            ->everyFiveMinutes()     ->everyTenMinutes()
 *   ->everyFifteenMinutes()    ->everyThirtyMinutes()
 *   ->hourly()                 ->hourlyAt(30)
 *   ->daily()                  ->dailyAt('08:00')       ->twiceDaily(1, 13)
 *   ->weekly()                 ->weeklyOn(1, '08:00')   (1=Monday)
 *   ->monthly()                ->monthlyOn(15, '08:00')
 *   ->quarterly()              ->yearly()
 *   ->weekdays()               ->weekends()
 *   ->cron('0 8 * * 1-5')     (raw cron expression)
 *
 * Time-window constraints:
 *   ->between('08:00', '17:00')       // only during office hours
 *   ->unlessBetween('22:00', '06:00') // skip overnight
 *   ->when(fn() => !isMaintenanceMode())
 *   ->skip(fn() => Cache::has('scheduler_paused'))
 */
class Kernel
{
    public function schedule(Scheduler $scheduler): void
    {
        // Example: clear expired cache entries every day at midnight
        // $scheduler->command('cache:clear')->daily()->description('Daily cache flush');

        // Example: send weekly digest every Monday at 8am
        // $scheduler->command('emails:digest')->weeklyOn(1, '08:00')->description('Weekly email digest');

        // Example: arbitrary callable every 5 minutes during business hours
        // $scheduler->call(function () {
        //     // your code
        // })->everyFiveMinutes()->between('08:00', '18:00')->name('heartbeat');

        // Heartbeat — logs every minute so you know the scheduler is alive
        $scheduler->call(function () {
            $msg = 'Scheduler heartbeat: ' . date('Y-m-d H:i:s');
            $log = defined('NEMESIS_BASE_PATH')
                ? NEMESIS_BASE_PATH . '/storage/logs/scheduler.log'
                : sys_get_temp_dir() . '/nemesis_scheduler.log';
            $dir = dirname($log);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            file_put_contents($log, $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
        })->everyMinute()->name('heartbeat')->description('Scheduler heartbeat');
    }
}
