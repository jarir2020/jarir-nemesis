<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 7 — Events | Created: 2026-04-03

namespace Nemesis\Events;

use Nemesis\Contracts\EventInterface;

/**
 * Base class for all application events.
 *
 * Usage:
 *   class UserRegistered extends Event {
 *       public function __construct(public readonly User $user) {}
 *   }
 *   EventDispatcher::dispatch(new UserRegistered($user));
 */
abstract class Event implements EventInterface
{
    /** Set to true in a listener to stop propagation to subsequent listeners. */
    private bool $propagationStopped = false;

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
