<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 18 — Task Scheduler | 2026-04-06

namespace Nemesis\Console;

/**
 * Scheduler — cron-style task runner.
 *
 * Cron entry (run every minute):
 *   * * * * * php /path/to/your/project/nemesis schedule:run >> /dev/null 2>&1
 *
 * Register tasks in app/Console/Kernel.php:
 *
 *   public function schedule(Scheduler $scheduler): void
 *   {
 *       $scheduler->command('cache:clear')->daily()->description('Clear cache');
 *       $scheduler->call(fn() => doWork())->hourly()->name('do-work');
 *       $scheduler->job(new SendReportJob())->cron('0 8 * * 1'); // Monday 8am
 *   }
 */
class Scheduler
{
    /** @var list<ScheduledTask> */
    private array $tasks = [];

    private string $logPath = '';

    public function __construct()
    {
        $this->logPath = defined('NEMESIS_BASE_PATH')
            ? NEMESIS_BASE_PATH . '/storage/logs/scheduler.log'
            : '';
    }

    // -------------------------------------------------------------------------
    // Builder API
    // -------------------------------------------------------------------------

    /**
     * Schedule a nemesis CLI command (e.g. 'cache:clear').
     */
    public function command(string $signature, array $arguments = []): ScheduledTask
    {
        $bin  = defined('NEMESIS_BIN')
            ? NEMESIS_BIN
            : ('php ' . (defined('NEMESIS_BASE_PATH') ? NEMESIS_BASE_PATH . '/nemesis' : 'nemesis'));
        $args = implode(' ', array_map('escapeshellarg', $arguments));
        $cmd  = trim("{$bin} {$signature} {$args}");

        $task = new ScheduledTask(function () use ($cmd) {
            passthru($cmd);
        });
        $task->name($signature);
        $this->tasks[] = $task;
        return $task;
    }

    /**
     * Schedule an arbitrary callable.
     */
    public function call(callable $callback, string $description = ''): ScheduledTask
    {
        $task = new ScheduledTask(\Closure::fromCallable($callback));
        if ($description) $task->description($description);
        $this->tasks[] = $task;
        return $task;
    }

    /**
     * Schedule a dispatchable Job object.
     * The job must implement a handle() method.
     */
    public function job(object $job): ScheduledTask
    {
        $task = new ScheduledTask(function () use ($job) {
            if (method_exists($job, 'handle')) {
                $job->handle();
            } else {
                throw new \RuntimeException(
                    'Scheduled job ' . get_class($job) . ' must implement handle()'
                );
            }
        });
        $task->name(get_class($job));
        $this->tasks[] = $task;
        return $task;
    }

    // -------------------------------------------------------------------------
    // Runner
    // -------------------------------------------------------------------------

    /**
     * Run all tasks that are due now.
     * Returns the number of tasks that ran.
     */
    public function run(\DateTimeInterface $now = null): int
    {
        $ran = 0;
        foreach ($this->tasks as $task) {
            if ($task->isDue($now)) {
                $this->runTask($task);
                $ran++;
            }
        }
        return $ran;
    }

    private function runTask(ScheduledTask $task): void
    {
        $name  = $task->getName() ?? ('task_' . spl_object_id($task)); // always string
        $mutex = $this->acquireMutex($name);

        if ($mutex === false) {
            $this->log("[SKIP] {$name} — already running (mutex held)");
            return;
        }

        $start = microtime(true);
        $this->log("[START] {$name}");

        ob_start();
        try {
            $task->run();
            $out     = ob_get_clean();
            $elapsed = round(microtime(true) - $start, 3);
            $this->log("[DONE]  {$name} ({$elapsed}s)" . ($out ? "\n" . trim($out) : ''));
        } catch (\Throwable $e) {
            ob_get_clean();
            $this->log("[ERROR] {$name}: " . $e->getMessage());
        } finally {
            $this->releaseMutex($mutex);
        }
    }

    // -------------------------------------------------------------------------
    // Mutex (file-based overlap protection)
    // -------------------------------------------------------------------------

    /**
     * @return resource|false  resource on success, false if lock could not be acquired
     */
    private function acquireMutex(string $name): mixed
    {
        $dir = sys_get_temp_dir() . '/nemesis_scheduler';
        if (!is_dir($dir)) @mkdir($dir, 0700, true);

        $path = $dir . '/' . md5($name) . '.lock';
        $fh   = fopen($path, 'c');
        if (!$fh) return false;

        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);
            return false;
        }

        return $fh;
    }

    private function releaseMutex(mixed $fh): void
    {
        if (is_resource($fh)) {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    private function log(string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;

        if ($this->logPath) {
            $dir = dirname($this->logPath);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            file_put_contents($this->logPath, $line, FILE_APPEND | LOCK_EX);
        }

        echo $line;
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /** @return list<ScheduledTask> */
    public function getTasks(): array { return $this->tasks; }

    /** @return list<ScheduledTask> Tasks that are due right now. */
    public function getDueTasks(\DateTimeInterface $now = null): array
    {
        return array_values(array_filter($this->tasks, fn($t) => $t->isDue($now)));
    }

    public function setLogPath(string $path): void { $this->logPath = $path; }
}
