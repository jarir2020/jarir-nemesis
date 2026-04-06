<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 20 — Broadcasting: Channel | 2026-04-06

namespace Nemesis\Broadcasting;

/**
 * Channel — value object representing a broadcast channel.
 *
 * Naming convention (Pusher-compatible):
 *   public   channels: any name not starting with 'private-' or 'presence-'
 *   private  channels: prefixed with 'private-'
 *   presence channels: prefixed with 'presence-'
 *
 * Examples:
 *   new Channel('orders')           // public
 *   new Channel('private-orders')   // private — requires auth
 *   new Channel('presence-room.1')  // presence — requires auth + user data
 */
readonly class Channel
{
    public const TYPE_PUBLIC   = 'public';
    public const TYPE_PRIVATE  = 'private';
    public const TYPE_PRESENCE = 'presence';

    public readonly string $type;

    public function __construct(public readonly string $name)
    {
        $this->type = self::detectType($name);
    }

    public static function detectType(string $name): string
    {
        if (str_starts_with($name, 'presence-')) return self::TYPE_PRESENCE;
        if (str_starts_with($name, 'private-'))  return self::TYPE_PRIVATE;
        return self::TYPE_PUBLIC;
    }

    public function isPublic(): bool   { return $this->type === self::TYPE_PUBLIC; }
    public function isPrivate(): bool  { return $this->type === self::TYPE_PRIVATE; }
    public function isPresence(): bool { return $this->type === self::TYPE_PRESENCE; }

    /** Whether this channel requires client-side authentication. */
    public function requiresAuth(): bool
    {
        return $this->type !== self::TYPE_PUBLIC;
    }

    public function __toString(): string { return $this->name; }
}
