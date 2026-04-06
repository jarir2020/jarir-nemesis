<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 20 — Broadcasting: ChannelManager | 2026-04-06

namespace Nemesis\Broadcasting;

/**
 * ChannelManager — tracks which socket IDs are subscribed to which channels.
 *
 * Each entry:
 *   $subscriptions[$channelName][$socketId] = $meta
 *
 * $meta for public channels:  []
 * $meta for presence channels: ['user_id' => ..., 'user_info' => [...]]
 */
class ChannelManager
{
    /** @var array<string, array<string, array>> channel → socketId → meta */
    private array $subscriptions = [];

    /** @var array<string, list<callable>> channel authorization callbacks */
    private array $authCallbacks = [];

    // -------------------------------------------------------------------------
    // Subscriptions
    // -------------------------------------------------------------------------

    /**
     * Subscribe a socket to a channel.
     *
     * @param array $meta  Optional metadata (used by presence channels).
     */
    public function subscribe(string $socketId, string $channel, array $meta = []): void
    {
        $this->subscriptions[$channel][$socketId] = $meta;
    }

    /** Remove a socket from a specific channel. */
    public function unsubscribe(string $socketId, string $channel): void
    {
        unset($this->subscriptions[$channel][$socketId]);
        if (empty($this->subscriptions[$channel])) {
            unset($this->subscriptions[$channel]);
        }
    }

    /** Remove a socket from ALL channels (called on disconnect). */
    public function unsubscribeAll(string $socketId): void
    {
        foreach (array_keys($this->subscriptions) as $channel) {
            unset($this->subscriptions[$channel][$socketId]);
            if (empty($this->subscriptions[$channel])) {
                unset($this->subscriptions[$channel]);
            }
        }
    }

    /** Check if a socket is subscribed to a channel. */
    public function isSubscribed(string $socketId, string $channel): bool
    {
        return isset($this->subscriptions[$channel][$socketId]);
    }

    /**
     * Get all socket IDs subscribed to a channel.
     * @return list<string>
     */
    public function subscribers(string $channel): array
    {
        return array_keys($this->subscriptions[$channel] ?? []);
    }

    /**
     * Get all channels a socket is subscribed to.
     * @return list<string>
     */
    public function channelsFor(string $socketId): array
    {
        $channels = [];
        foreach ($this->subscriptions as $channel => $sockets) {
            if (isset($sockets[$socketId])) {
                $channels[] = $channel;
            }
        }
        return $channels;
    }

    /** Get all active channels. */
    public function channels(): array { return array_keys($this->subscriptions); }

    /** Count of subscribers on a channel. */
    public function subscriberCount(string $channel): int
    {
        return count($this->subscriptions[$channel] ?? []);
    }

    // -------------------------------------------------------------------------
    // Presence channel member data
    // -------------------------------------------------------------------------

    /**
     * Get presence channel members.
     * @return array<string, array>  socketId → meta
     */
    public function presenceMembers(string $channel): array
    {
        return $this->subscriptions[$channel] ?? [];
    }

    /**
     * Get member metadata for a specific socket on a presence channel.
     */
    public function memberMeta(string $socketId, string $channel): array
    {
        return $this->subscriptions[$channel][$socketId] ?? [];
    }

    // -------------------------------------------------------------------------
    // Channel authorization callbacks
    // -------------------------------------------------------------------------

    /**
     * Register an authorization callback for a channel pattern.
     * The callback receives ($socketId, $channelName, $userId) and returns bool.
     *
     *   $manager->authChannel('private-orders.*', function($socketId, $channel, $userId) {
     *       return Order::where('user_id', $userId)->exists();
     *   });
     */
    public function authChannel(string $pattern, callable $callback): void
    {
        $this->authCallbacks[$pattern] = $callback;
    }

    /**
     * Check whether a socket is authorized for a channel.
     * Falls back to true for public channels.
     */
    public function isAuthorized(string $socketId, string $channel, mixed $userId = null): bool
    {
        $ch = new Channel($channel);
        if ($ch->isPublic()) return true;

        foreach ($this->authCallbacks as $pattern => $callback) {
            if ($pattern === $channel || fnmatch($pattern, $channel)) {
                return (bool) $callback($socketId, $channel, $userId);
            }
        }

        // No callback registered — default deny for private/presence
        return false;
    }

    // -------------------------------------------------------------------------
    // State
    // -------------------------------------------------------------------------

    public function reset(): void { $this->subscriptions = []; }

    /** @return array Raw subscriptions (for debugging/testing). */
    public function all(): array { return $this->subscriptions; }
}
