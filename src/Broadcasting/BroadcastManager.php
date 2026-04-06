<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 20 — Broadcasting: BroadcastManager facade | 2026-04-06

namespace Nemesis\Broadcasting;

use Nemesis\Broadcasting\Contracts\BroadcasterContract;
use Nemesis\Broadcasting\Contracts\ShouldBroadcast;

/**
 * BroadcastManager — static facade for broadcasting events.
 *
 * Usage:
 *   // Broadcast to a channel
 *   Broadcast::to('orders')->event('OrderUpdated', ['order_id' => 1]);
 *
 *   // Broadcast multiple channels
 *   Broadcast::toChannels(['orders', 'private-admin'])->event('OrderUpdated', $data);
 *
 *   // Broadcast a ShouldBroadcast event object
 *   Broadcast::dispatch(new OrderUpdated($order));
 *
 * Testing:
 *   Broadcast::fake();
 *   // ... trigger code that broadcasts ...
 *   Broadcast::assertBroadcastOn('orders', 'OrderUpdated');
 *   Broadcast::assertNothingBroadcast();
 *   Broadcast::resetFakes();
 */
class BroadcastManager
{
    // -------------------------------------------------------------------------
    // Fake system (test support)
    // -------------------------------------------------------------------------

    private static bool  $faking   = false;
    private static array $recorded = [];

    public static function fake(): void
    {
        self::$faking   = true;
        self::$recorded = [];
    }

    public static function resetFakes(): void
    {
        self::$faking   = false;
        self::$recorded = [];
    }

    public static function isFaking(): bool { return self::$faking; }

    /** @internal */
    public static function record(array|string $channels, string $event, array $payload): void
    {
        $channels = (array) $channels;
        foreach ($channels as $channel) {
            self::$recorded[] = [
                'channel' => $channel,
                'event'   => $event,
                'payload' => $payload,
            ];
        }
    }

    public static function recorded(): array { return self::$recorded; }

    // -------------------------------------------------------------------------
    // Assertions
    // -------------------------------------------------------------------------

    /**
     * Assert an event was broadcast on a specific channel.
     * @throws \RuntimeException
     */
    public static function assertBroadcastOn(string $channel, string $event = null): void
    {
        foreach (self::$recorded as $r) {
            if ($r['channel'] === $channel && ($event === null || $r['event'] === $event)) {
                return;
            }
        }

        $msg = $event
            ? "Expected event '{$event}' was not broadcast on channel '{$channel}'."
            : "Nothing was broadcast on channel '{$channel}'.";

        throw new \RuntimeException($msg . ' Recorded: ' . self::describeRecorded());
    }

    /**
     * Assert nothing was broadcast.
     * @throws \RuntimeException
     */
    public static function assertNothingBroadcast(): void
    {
        if (!empty(self::$recorded)) {
            throw new \RuntimeException(
                'Expected nothing to be broadcast, but ' . count(self::$recorded) . ' event(s) were recorded.'
            );
        }
    }

    /**
     * Assert the number of broadcast events recorded.
     * @throws \RuntimeException
     */
    public static function assertBroadcastCount(int $count): void
    {
        $actual = count(self::$recorded);
        if ($actual !== $count) {
            throw new \RuntimeException(
                "Expected {$count} broadcast(s), but {$actual} were recorded."
            );
        }
    }

    /**
     * Assert a broadcast matching the given callback was made.
     * @param callable(array{channel:string,event:string,payload:array}):bool $callback
     * @throws \RuntimeException
     */
    public static function assertBroadcast(callable $callback): void
    {
        foreach (self::$recorded as $r) {
            if ($callback($r) === true) return;
        }
        throw new \RuntimeException(
            'No broadcast matched the given assertion. Recorded: ' . self::describeRecorded()
        );
    }

    private static function describeRecorded(): string
    {
        if (empty(self::$recorded)) return '(none)';
        return implode(', ', array_map(
            fn($r) => "{$r['event']} → {$r['channel']}",
            self::$recorded
        ));
    }

    // -------------------------------------------------------------------------
    // Fluent builder
    // -------------------------------------------------------------------------

    /** Target a single channel. */
    public static function to(string $channel): BroadcastPendingBroadcast
    {
        return new BroadcastPendingBroadcast([$channel]);
    }

    /** Target multiple channels. */
    public static function toChannels(array $channels): BroadcastPendingBroadcast
    {
        return new BroadcastPendingBroadcast($channels);
    }

    // -------------------------------------------------------------------------
    // Dispatch a ShouldBroadcast event
    // -------------------------------------------------------------------------

    public static function dispatch(ShouldBroadcast $event): void
    {
        $channels = (array) $event->broadcastOn();
        $name     = $event->broadcastAs();
        $payload  = $event->broadcastWith();

        self::send($channels, $name, $payload);
    }

    // -------------------------------------------------------------------------
    // Core send
    // -------------------------------------------------------------------------

    /** @internal */
    public static function send(array $channels, string $event, array $payload): void
    {
        self::record($channels, $event, $payload);

        if (self::$faking) return;

        // Real broadcasting: write to the WebSocket server's shared channel store
        // (in-process via shared ChannelManager, or via a socket/Redis pub/sub)
        // For now, log to storage/logs/broadcast.log
        $line = json_encode([
            'channels' => $channels,
            'event'    => $event,
            'payload'  => $payload,
            'ts'       => microtime(true),
        ]) . PHP_EOL;

        $logDir = defined('NEMESIS_BASE_PATH')
            ? NEMESIS_BASE_PATH . '/storage/logs'
            : sys_get_temp_dir();

        @mkdir($logDir, 0755, true);
        file_put_contents($logDir . '/broadcast.log', $line, FILE_APPEND | LOCK_EX);
    }
}

// =============================================================================
// BroadcastPendingBroadcast — fluent pending broadcast builder
// =============================================================================

class BroadcastPendingBroadcast
{
    public function __construct(private readonly array $channels) {}

    /** Send an event to the targeted channels. */
    public function event(string $name, array $payload = []): void
    {
        BroadcastManager::send($this->channels, $name, $payload);
    }

    /** Alias for event(). */
    public function dispatch(string $name, array $payload = []): void
    {
        $this->event($name, $payload);
    }
}
