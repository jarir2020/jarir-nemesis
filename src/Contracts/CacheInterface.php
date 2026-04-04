<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Created: 2026-04-02
namespace Nemesis\Contracts;

/**
 * Stable public contract for cache drivers.
 */
interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, int $seconds = 3600): bool;
    public function forget(string $key): bool;
    public function clear(): bool;
    public function has(string $key): bool;
}
