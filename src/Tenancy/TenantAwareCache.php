<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 21 — Multi-tenancy: TenantAwareCache | 2026-04-06

namespace Nemesis\Tenancy;

use Nemesis\Core\Cache;

/**
 * TenantAwareCache — Cache wrapper that automatically namespaces every key
 * with the current tenant's prefix so tenants never share cached values.
 *
 * Usage:
 *   $cache = new TenantAwareCache();
 *   $cache->set('orders', $orders, 300);   // stored as "tenant_7_orders"
 *   $cache->get('orders');                 // reads "tenant_7_orders"
 *   $cache->forget('orders');
 *   $cache->flush();                       // clears ALL keys for this tenant
 *
 * With a custom prefix:
 *   $cache = new TenantAwareCache('acme_');
 *
 * Bypass (admin panel reading raw keys):
 *   TenantAwareCache::raw()->get('some_global_key');
 */
class TenantAwareCache
{
    private string $prefix;

    public function __construct(?string $prefix = null)
    {
        // Prefer explicitly-passed prefix, then live TenantManager, then empty.
        $this->prefix = $prefix ?? TenantManager::cachePrefix();
    }

    // -------------------------------------------------------------------------
    // Core cache operations
    // -------------------------------------------------------------------------

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return (bool) Cache::set($this->prefixed($key), $value, $ttl);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($this->prefixed($key), $default);
    }

    public function forget(string $key): bool
    {
        return (bool) Cache::forget($this->prefixed($key));
    }

    /**
     * Retrieve a cached value or compute and store it.
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $prefixed = $this->prefixed($key);
        $cached   = Cache::get($prefixed);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        Cache::set($prefixed, $value, $ttl);
        return $value;
    }

    /**
     * Store value forever (TTL = 1 year).
     */
    public function forever(string $key, mixed $value): bool
    {
        return (bool) Cache::set($this->prefixed($key), $value, 31_536_000);
    }

    /**
     * Check if a key exists in the tenant's cache namespace.
     */
    public function has(string $key): bool
    {
        return Cache::get($this->prefixed($key)) !== null;
    }

    // -------------------------------------------------------------------------
    // Bulk operations
    // -------------------------------------------------------------------------

    /**
     * Store multiple key-value pairs.
     * @param array<string, mixed> $items
     */
    public function setMany(array $items, int $ttl = 3600): void
    {
        foreach ($items as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }
    }

    /**
     * Retrieve multiple keys at once.
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    public function getMany(array $keys, mixed $default = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    /**
     * Forget multiple keys at once.
     */
    public function forgetMany(array $keys): void
    {
        foreach ($keys as $key) {
            $this->forget($key);
        }
    }

    // -------------------------------------------------------------------------
    // Introspection
    // -------------------------------------------------------------------------

    public function getPrefix(): string { return $this->prefix; }

    /**
     * Full cache key as stored (with tenant prefix).
     */
    public function prefixed(string $key): string
    {
        return $this->prefix . $key;
    }

    // -------------------------------------------------------------------------
    // Static factories
    // -------------------------------------------------------------------------

    /**
     * Cache instance scoped to a specific tenant (by ID).
     */
    public static function forTenant(int|string $tenantId): static
    {
        return new static("tenant_{$tenantId}_");
    }

    /**
     * Global cache (no tenant prefix) — use for admin-level or cross-tenant data.
     */
    public static function raw(): static
    {
        return new static('');
    }

    /**
     * Cache for the currently-set tenant (shortcut for new TenantAwareCache()).
     */
    public static function current(): static
    {
        return new static();
    }
}
