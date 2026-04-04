<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 8 — Testing Infrastructure | Created: 2026-04-02

namespace Nemesis\Testing\Fakes;

/**
 * QueueFake — captures pushed jobs instead of queueing them.
 *
 * Usage:
 *   $fake = new QueueFake();
 *   $fake->push(SendWelcomeEmail::class, ['user_id' => 1]);
 *   $fake->assertPushed(SendWelcomeEmail::class);
 */
class QueueFake
{
    /** @var array<string, list<array<string, mixed>>> job class → list of payloads */
    protected array $jobs = [];

    // -------------------------------------------------------------------------
    // Capture
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $payload
     */
    public function push(string $job, array $payload = [], string $queue = 'default'): void
    {
        $this->jobs[$job][] = ['payload' => $payload, 'queue' => $queue];
    }

    // -------------------------------------------------------------------------
    // Assertions
    // -------------------------------------------------------------------------

    public function assertPushed(string $job, ?callable $callback = null): void
    {
        if (!isset($this->jobs[$job])) {
            throw new \Exception("Job [{$job}] was not pushed.");
        }
        if ($callback !== null) {
            foreach ($this->jobs[$job] as $entry) {
                if ($callback($entry['payload']) === true) return;
            }
            throw new \Exception("Job [{$job}] was pushed but callback did not match any payload.");
        }
    }

    public function assertNotPushed(string $job): void
    {
        if (isset($this->jobs[$job])) {
            throw new \Exception("Job [{$job}] was unexpectedly pushed.");
        }
    }

    public function assertPushedTimes(string $job, int $times): void
    {
        $actual = count($this->jobs[$job] ?? []);
        if ($actual !== $times) {
            throw new \Exception("Job [{$job}] pushed {$actual} time(s), expected {$times}.");
        }
    }

    public function assertNothingPushed(): void
    {
        if (!empty($this->jobs)) {
            throw new \Exception(
                "Jobs were unexpectedly pushed: " . implode(', ', array_keys($this->jobs))
            );
        }
    }

    // -------------------------------------------------------------------------
    // Inspection
    // -------------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function pushed(string $job): array
    {
        return $this->jobs[$job] ?? [];
    }

    public function hasPushed(string $job): bool
    {
        return !empty($this->jobs[$job]);
    }

    public function flush(): void
    {
        $this->jobs = [];
    }
}
