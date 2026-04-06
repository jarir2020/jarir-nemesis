<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 21 — Multi-tenancy: TenantScope trait | 2026-04-06

namespace Nemesis\Tenancy;

use Nemesis\Core\Database;

/**
 * TenantScope — shared-database multi-tenancy trait.
 *
 * Mix into any Model class that stores tenant data in a shared DB.
 * All queries will be automatically scoped to the current tenant.
 *
 * Usage:
 *   class Order extends Model {
 *       use TenantScope;
 *   }
 *
 *   // With a current tenant set:
 *   Order::allForTenant();         // SELECT * FROM orders WHERE tenant_id = ?
 *   Order::findForTenant($id);     // SELECT * FROM orders WHERE id = ? AND tenant_id = ?
 *   Order::createForTenant([...]);  // INSERT with tenant_id auto-filled
 *
 * The tenant_id column must exist on your table. Add it in your migration:
 *   ALTER TABLE orders ADD COLUMN tenant_id INTEGER NOT NULL DEFAULT 0;
 */
trait TenantScope
{
    // -------------------------------------------------------------------------
    // Scoped query helpers (works without full ORM)
    // -------------------------------------------------------------------------

    /**
     * Get the current tenant ID (null if no tenant set).
     */
    public static function currentTenantId(): int|string|null
    {
        return TenantManager::id();
    }

    /**
     * Fetch all rows for the current tenant.
     * @return list<array>
     */
    public static function allForTenant(): array
    {
        $tenantId = TenantManager::id();
        if ($tenantId === null) return [];

        $table = self::getTable();
        return Database::view(
            "SELECT * FROM {$table} WHERE tenant_id = ? ORDER BY id DESC",
            [$tenantId]
        );
    }

    /**
     * Find a single row by ID scoped to the current tenant.
     * @return array|null
     */
    public static function findForTenant(int|string $id): ?array
    {
        $tenantId = TenantManager::id();
        if ($tenantId === null) return null;

        $table = self::getTable();
        $rows  = Database::view(
            "SELECT * FROM {$table} WHERE id = ? AND tenant_id = ? LIMIT 1",
            [$id, $tenantId]
        );
        return $rows[0] ?? null;
    }

    /**
     * Count rows for the current tenant.
     */
    public static function countForTenant(): int
    {
        $tenantId = TenantManager::id();
        if ($tenantId === null) return 0;

        $table = self::getTable();
        $rows  = Database::view(
            "SELECT COUNT(*) as cnt FROM {$table} WHERE tenant_id = ?",
            [$tenantId]
        );
        return (int) ($rows[0]['cnt'] ?? 0);
    }

    /**
     * Insert a row, automatically injecting the current tenant_id.
     * @return int  Last insert ID
     */
    public static function createForTenant(array $attributes): int
    {
        $tenantId = TenantManager::id();
        if ($tenantId === null) {
            throw new \RuntimeException('Cannot create tenant-scoped record: no current tenant set.');
        }

        $attributes['tenant_id'] = $tenantId;
        $table   = self::getTable();
        $columns = implode(', ', array_keys($attributes));
        $placeholders = implode(', ', array_fill(0, count($attributes), '?'));

        Database::statement(
            "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})",
            array_values($attributes)
        );

        return (int) Database::connect()->lastInsertId();
    }

    /**
     * Delete a row scoped to the current tenant.
     */
    public static function deleteForTenant(int|string $id): bool
    {
        $tenantId = TenantManager::id();
        if ($tenantId === null) return false;

        $table = self::getTable();
        Database::statement(
            "DELETE FROM {$table} WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
        return true;
    }

    /**
     * WHERE clause fragment + bindings for the current tenant.
     * Use this when building custom queries.
     *
     * @return array{clause: string, bindings: list<mixed>}
     */
    public static function tenantScope(): array
    {
        $tenantId = TenantManager::id();
        return [
            'clause'   => $tenantId !== null ? 'tenant_id = ?' : '1=1',
            'bindings' => $tenantId !== null ? [$tenantId] : [],
        ];
    }

    // -------------------------------------------------------------------------
    // Auto-fill on save (for ORM-based usage)
    // -------------------------------------------------------------------------

    /**
     * If using the Model base class, override save() to auto-fill tenant_id.
     */
    public function fillTenantId(): void
    {
        if (!isset($this->attributes['tenant_id'])) {
            $this->attributes['tenant_id'] = TenantManager::id();
        }
    }

    // -------------------------------------------------------------------------
    // Table name helper
    // -------------------------------------------------------------------------

    private static function getTable(): string
    {
        // Works with Model base class (which has $table property)
        // Falls back to snake_case class name + 's'
        if (isset(static::$table)) return static::$table;

        $class = (new \ReflectionClass(static::class))->getShortName();
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($class))) . 's';
    }
}
