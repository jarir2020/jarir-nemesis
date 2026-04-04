<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 9 — QueueDriver interface | Updated: 2026-04-03

namespace Nemesis\Queue;

interface QueueDriver
{
    /** Push a job onto the named queue. Returns truthy on success. */
    public function push(Job $job, string $queue = 'default'): mixed;

    /** Pop and reserve the next available job row. Returns null if empty. */
    public function pop(string $queue = 'default'): ?array;

    /** Permanently remove a job (after success or permanent failure). */
    public function delete(int|string $id): bool;

    /** Release a job back to the queue with an optional delay (seconds). */
    public function release(int|string $id, int $delay = 0): bool;
}
