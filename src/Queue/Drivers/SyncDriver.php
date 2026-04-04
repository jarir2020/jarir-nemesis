<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 9 — SyncDriver updated | Updated: 2026-04-03

namespace Nemesis\Queue\Drivers;

use Nemesis\Queue\QueueDriver;
use Nemesis\Queue\Job;

/**
 * Sync driver — runs jobs immediately in-process.
 * No queue table needed. Useful for development and testing.
 */
class SyncDriver implements QueueDriver
{
    public function push(Job $job, string $queue = 'default'): mixed
    {
        $job->handle();
        // Dispatch chained jobs synchronously too
        foreach ($job->getChain() as $next) {
            $this->push($next, $queue);
        }
        return true;
    }

    public function pop(string $queue = 'default'): ?array   { return null; }
    public function delete(int|string $id): bool             { return true; }
    public function release(int|string $id, int $delay = 0): bool { return true; }
}
