<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Created: 2026-04-02
namespace Nemesis\Contracts;

/**
 * Stable public contract for queue drivers.
 */
interface QueueInterface
{
    public function push(object $job, int $delay = 0): bool;
    public function pop(): object|null;
    public function size(): int;
}
