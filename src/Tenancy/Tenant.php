<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 21 — Multi-tenancy: Tenant DTO | 2026-04-06

namespace Nemesis\Tenancy;

/**
 * Tenant — value object representing a single tenant.
 *
 * DB schema (auto-created by TenantManager::ensureTable()):
 *   id, name, slug, domain, database, active, meta, created_at
 */
class Tenant
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $domain   = '',
        public readonly string $database = '',  // used by DB-per-tenant strategy
        public readonly bool   $active   = true,
        public readonly array  $meta     = [],
        public readonly string $createdAt = '',
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:        (int)    ($row['id']         ?? 0),
            name:               ($row['name']        ?? ''),
            slug:               ($row['slug']        ?? ''),
            domain:             ($row['domain']      ?? ''),
            database:           ($row['database']    ?? ''),
            active:    (bool)   ($row['active']      ?? true),
            meta:      (array)  json_decode((string) ($row['meta'] ?? '{}'), true),
            createdAt:          ($row['created_at']  ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'slug'       => $this->slug,
            'domain'     => $this->domain,
            'database'   => $this->database,
            'active'     => $this->active,
            'meta'       => $this->meta,
            'created_at' => $this->createdAt,
        ];
    }

    /** Cache key prefix for this tenant. */
    public function cachePrefix(): string
    {
        return "tenant_{$this->id}_";
    }

    /** Storage disk path for this tenant. */
    public function storagePath(string $base = ''): string
    {
        $base = $base ?: (defined('NEMESIS_BASE_PATH') ? NEMESIS_BASE_PATH . '/storage' : sys_get_temp_dir());
        return rtrim($base, '/') . '/tenants/' . $this->slug;
    }

    public function __toString(): string { return $this->slug; }
}
