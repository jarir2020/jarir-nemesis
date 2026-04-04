<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Created: 2026-04-02
namespace Nemesis\Contracts;

/**
 * Stable public contract for event listeners.
 */
interface ListenerInterface
{
    public function handle(object $event): void;
}
