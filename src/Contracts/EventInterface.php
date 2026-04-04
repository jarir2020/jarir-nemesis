<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Created: 2026-04-02
namespace Nemesis\Contracts;

/**
 * Marker interface for typed application events.
 * All event classes should implement this.
 *
 * Usage:
 *   class UserRegistered implements EventInterface {
 *       public function __construct(public readonly User $user) {}
 *   }
 */
interface EventInterface {}
