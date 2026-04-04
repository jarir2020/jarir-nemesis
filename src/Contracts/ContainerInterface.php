<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Created: 2026-04-02
namespace Nemesis\Contracts;

/**
 * Stable public contract for the Nemesis service container.
 * Extends PSR-11 with Nemesis-specific binding methods.
 */
interface ContainerInterface
{
    /**
     * Find an entry by its identifier and return it.
     */
    public function get(string $id): mixed;

    /**
     * Returns true if the container can return an entry for the given identifier.
     */
    public function has(string $id): bool;

    /**
     * Resolve a class with auto-wiring.
     */
    public function make(string $abstract, array $parameters = []): mixed;

    /**
     * Register a binding in the container.
     */
    public function bind(string $abstract, callable|string $concrete): void;

    /**
     * Register a shared (singleton) binding.
     */
    public function singleton(string $abstract, callable|string $concrete): void;
}
