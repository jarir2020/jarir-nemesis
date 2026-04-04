<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 8 — Testing Infrastructure | Created: 2026-04-02

namespace Nemesis\Testing\Fakes;

/**
 * EventFake — captures dispatched events instead of executing listeners.
 *
 * Usage:
 *   $fake = EventFake::swap();          // replaces the real dispatcher
 *   $this->fireEvent('user.created', $user);
 *   $fake->assertDispatched('user.created');
 */
class EventFake
{
    /** @var array<string, list<mixed>> event name → list of payloads */
    protected array $dispatched = [];

    // -------------------------------------------------------------------------
    // Capture
    // -------------------------------------------------------------------------

    public function dispatch(string $event, mixed $payload = null): void
    {
        $this->dispatched[$event][] = $payload;
    }

    // -------------------------------------------------------------------------
    // Assertions
    // -------------------------------------------------------------------------

    public function assertDispatched(string $event, ?callable $callback = null): void
    {
        if (!isset($this->dispatched[$event])) {
            throw new \Exception("Event [{$event}] was not dispatched.");
        }
        if ($callback !== null) {
            foreach ($this->dispatched[$event] as $payload) {
                if ($callback($payload) === true) return;
            }
            throw new \Exception("Event [{$event}] was dispatched but callback did not match any payload.");
        }
    }

    public function assertNotDispatched(string $event): void
    {
        if (isset($this->dispatched[$event])) {
            throw new \Exception("Event [{$event}] was unexpectedly dispatched.");
        }
    }

    public function assertDispatchedTimes(string $event, int $times): void
    {
        $actual = count($this->dispatched[$event] ?? []);
        if ($actual !== $times) {
            throw new \Exception("Event [{$event}] dispatched {$actual} time(s), expected {$times}.");
        }
    }

    public function assertNothingDispatched(): void
    {
        if (!empty($this->dispatched)) {
            throw new \Exception(
                "Events were unexpectedly dispatched: " . implode(', ', array_keys($this->dispatched))
            );
        }
    }

    // -------------------------------------------------------------------------
    // Inspection
    // -------------------------------------------------------------------------

    /** @return list<mixed> */
    public function dispatched(string $event): array
    {
        return $this->dispatched[$event] ?? [];
    }

    public function hasDispatched(string $event): bool
    {
        return !empty($this->dispatched[$event]);
    }

    public function flush(): void
    {
        $this->dispatched = [];
    }
}
