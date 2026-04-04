<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 9 — Typed Config DTOs | Updated: 2026-04-03

namespace Nemesis\Config;

readonly class QueueConfig
{
    public function __construct(
        public string $driver,
        public string $table,
        public string $failedTable,
        public int    $retryAfter,
        public int    $maxTries,
    ) {}

    public static function fromEnv(): static
    {
        return new static(
            driver:      (string) (getenv('QUEUE_DRIVER')  ?: 'database'),
            table:       'jobs',
            failedTable: 'failed_jobs',
            retryAfter:  90,
            maxTries:    3,
        );
    }

    public static function required(): array
    {
        return ['QUEUE_DRIVER'];
    }
}
