<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 20 — Broadcasting: ShouldBroadcast interface | 2026-04-06

namespace Nemesis\Broadcasting\Contracts;

interface ShouldBroadcast
{
    /** Channel(s) to broadcast on. */
    public function broadcastOn(): string|array;

    /** Event name sent to clients. Defaults to class FQN if not implemented. */
    public function broadcastAs(): string;

    /** Payload to broadcast. Defaults to public properties. */
    public function broadcastWith(): array;
}
