<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 9 — Queue Job improvements | Updated: 2026-04-03

namespace Nemesis\Queue;

/**
 * Base job class.
 * Extend this and implement handle().
 *
 * Features:
 *   - $tries        — max attempts before moving to failed_jobs
 *   - $delay        — seconds to wait before first attempt
 *   - $timeout      — max seconds handle() may run (enforced by worker)
 *   - $backoff       — seconds between retries (int or array per attempt)
 *   - chain(Job[])  — jobs to run sequentially after this one
 *   - onFailure()   — hook called when all retries are exhausted
 */
abstract class Job
{
    public int        $tries   = 3;
    public int        $delay   = 0;
    public int        $timeout = 60;
    public int|array  $backoff = 0;

    /** Jobs to dispatch sequentially after this one succeeds. */
    protected array $chain = [];

    // -------------------------------------------------------------------------
    // Contract
    // -------------------------------------------------------------------------

    abstract public function handle(): void;

    // -------------------------------------------------------------------------
    // Chaining
    // -------------------------------------------------------------------------

    /** Attach a sequence of jobs to run after this one succeeds. */
    public function chain(array $jobs): static
    {
        $this->chain = $jobs;
        return $this;
    }

    public function getChain(): array
    {
        return $this->chain;
    }

    // -------------------------------------------------------------------------
    // Retry backoff
    // -------------------------------------------------------------------------

    /** Seconds to wait before the given attempt (1-indexed). */
    public function backoffFor(int $attempt): int
    {
        if (is_array($this->backoff)) {
            return (int) ($this->backoff[$attempt - 1] ?? end($this->backoff) ?: 0);
        }
        return $this->backoff;
    }

    // -------------------------------------------------------------------------
    // Failure hook
    // -------------------------------------------------------------------------

    /** Called when the job has exhausted all retries. Override to add custom handling. */
    public function onFailure(\Throwable $e): void
    {
        // Default: do nothing — failure is recorded in failed_jobs table
    }

    // -------------------------------------------------------------------------
    // Accessors (kept for backwards compat)
    // -------------------------------------------------------------------------

    public function getTries(): int  { return $this->tries; }
    public function getDelay(): int  { return $this->delay; }
}
