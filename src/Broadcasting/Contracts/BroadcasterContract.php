<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 20 — Broadcasting: BroadcasterContract | 2026-04-06

namespace Nemesis\Broadcasting\Contracts;

interface BroadcasterContract
{
    /**
     * Broadcast an event to one or more channels.
     *
     * @param string|list<string> $channels
     * @param string              $event   Event name (e.g. 'App\\Events\\OrderUpdated')
     * @param array               $payload Data to send
     */
    public function broadcast(string|array $channels, string $event, array $payload = []): void;
}
