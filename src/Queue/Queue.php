<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 9 — Queue improvements | Updated: 2026-04-03

namespace Nemesis\Queue;

/**
 * Queue facade — dispatch jobs to the configured driver.
 *
 * Basic:
 *   Queue::push(new SendEmailJob($user));
 *   Queue::later(60, new SendEmailJob($user));     // delay 60 s
 *   Queue::chain([new StepA(), new StepB()]);      // sequential
 *   Queue::batch([new JobA(), new JobB()]);         // independent batch
 *   Queue::failed()->retryAll();                   // retry all failed
 */
class Queue
{
    protected static ?QueueDriver $driver       = null;
    protected static ?FailedJobManager $failed  = null;

    // -------------------------------------------------------------------------
    // Driver
    // -------------------------------------------------------------------------

    protected static function driver(): QueueDriver
    {
        if (self::$driver !== null) return self::$driver;

        $name = strtolower((string) (getenv('QUEUE_DRIVER') ?: 'sync'));
        self::$driver = match ($name) {
            'database' => new Drivers\DatabaseDriver(),
            'redis'    => new Drivers\RedisDriver(),
            default    => new Drivers\SyncDriver(),
        };
        return self::$driver;
    }

    public static function setDriver(QueueDriver $driver): void
    {
        self::$driver = $driver;
    }

    public static function failed(): FailedJobManager
    {
        return self::$failed ??= new FailedJobManager();
    }

    // -------------------------------------------------------------------------
    // Dispatch
    // -------------------------------------------------------------------------

    public static function push(Job $job, string $queue = 'default'): bool
    {
        return (bool) self::driver()->push($job, $queue);
    }

    /** Push with delay (seconds). */
    public static function later(int $delay, Job $job, string $queue = 'default'): bool
    {
        $job->delay = $delay;
        return self::push($job, $queue);
    }

    /**
     * Chain: run jobs sequentially — first carries remaining as metadata.
     */
    public static function chain(array $jobs, string $queue = 'default'): bool
    {
        if (empty($jobs)) return true;
        $first = array_shift($jobs);
        if (!empty($jobs)) $first->chain($jobs);
        return self::push($first, $queue);
    }

    /**
     * Batch: dispatch all independently; returns count dispatched.
     */
    public static function batch(array $jobs, string $queue = 'default'): int
    {
        $count = 0;
        foreach ($jobs as $job) {
            if (self::push($job, $queue)) $count++;
        }
        return $count;
    }

    // -------------------------------------------------------------------------
    // Worker helper (used by queue:work CLI command)
    // -------------------------------------------------------------------------

    /**
     * Pop and process one job.  Returns true if a job was run (pass or fail).
     */
    public static function processNext(string $queue = 'default'): bool
    {
        $raw = self::driver()->pop($queue);
        if (!$raw) return false;

        $id  = $raw['id'] ?? null;
        $job = null;

        try {
            $job = unserialize($raw['payload'] ?? '');
            if (!$job instanceof Job) {
                throw new \RuntimeException('Queue payload is corrupt.');
            }

            $job->handle();

            // Dispatch chained jobs after success
            foreach ($job->getChain() as $next) {
                self::push($next, $queue);
            }

            if ($id !== null && method_exists(self::driver(), 'delete')) {
                self::driver()->delete($id);
            }

        } catch (\Throwable $e) {
            $attempts = (int) ($raw['attempts'] ?? 1);
            $maxTries = $job?->getTries() ?? 3;

            if ($attempts >= $maxTries) {
                if ($job) {
                    $job->onFailure($e);
                    self::failed()->store($job, $e, $queue);
                }
                if ($id !== null && method_exists(self::driver(), 'delete')) {
                    self::driver()->delete($id);
                }
            } else {
                $backoff = $job ? $job->backoffFor($attempts + 1) : 0;
                if ($id !== null && method_exists(self::driver(), 'release')) {
                    self::driver()->release($id, $backoff);
                }
            }
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Legacy
    // -------------------------------------------------------------------------

    /** @deprecated Use push() */
    public static function pop(string $queue = 'default'): mixed
    {
        return self::driver()->pop($queue);
    }
}
