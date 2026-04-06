<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 20 — Broadcasting: Broadcaster interface | 2026-04-06

namespace Nemesis\Broadcasting;

use Nemesis\Broadcasting\Contracts\BroadcasterContract;

/**
 * Broadcaster — named alias of BroadcasterContract.
 * Implementations: WebSocketServer, LogBroadcaster.
 */
interface Broadcaster extends BroadcasterContract {}

