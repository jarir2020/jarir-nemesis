<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 2 — SoftDeletes trait fixed | Updated: 2026-04-03

namespace Nemesis\Core\Traits;

use Nemesis\Core\Fluent;
use Nemesis\Support\Collection;

/**
 * SoftDeletes Trait — add to any Model to enable soft-delete behaviour.
 *
 * Usage:
 *   class Post extends Model {
 *       use SoftDeletes;
 *   }
 *
 * Behaviour:
 *   - delete() sets deleted_at instead of removing the row
 *   - Global scope excludes soft-deleted rows automatically
 *   - withTrashed() / onlyTrashed() / restore() exposed as static helpers
 */
trait SoftDeletes
{
    // -------------------------------------------------------------------------
    // Boot — register global scope that excludes deleted rows
    // -------------------------------------------------------------------------

    public static function bootSoftDeletes(): void
    {
        static::addGlobalScope('softDelete', function (\Nemesis\Core\Builder $builder): void {
            $builder->whereNull('deleted_at');
        });
    }

    // -------------------------------------------------------------------------
    // Soft delete / restore
    // -------------------------------------------------------------------------

    /**
     * Soft-delete: sets deleted_at to now.
     * Overrides Model::delete() — force-delete available via forceDelete().
     */
    public function delete(): bool
    {
        if ($this->fireModelEvent('deleting') === false) return false;

        $now = date('Y-m-d H:i:s');

        Fluent::table($this->getTable())
              ->where($this->primaryKey, '=', $this->getKey())
              ->update(['deleted_at' => $now]);

        $this->attributes['deleted_at'] = $now;
        $this->fireModelEvent('deleted');

        return true;
    }

    /** Hard-delete: permanently removes the row. */
    public function forceDelete(): bool
    {
        if ($this->fireModelEvent('forceDeleting') === false) return false;

        Fluent::table($this->getTable())
              ->where($this->primaryKey, '=', $this->getKey())
              ->delete();

        $this->exists = false;
        $this->fireModelEvent('forceDeleted');

        return true;
    }

    /** Restore a soft-deleted record. */
    public function restore(): static
    {
        $this->fireModelEvent('restoring');

        Fluent::table($this->getTable())
              ->where($this->primaryKey, '=', $this->getKey())
              ->update(['deleted_at' => null]);

        $this->attributes['deleted_at'] = null;
        $this->fireModelEvent('restored');

        return $this;
    }

    // -------------------------------------------------------------------------
    // Query helpers
    // -------------------------------------------------------------------------

    /** Return a Builder that includes soft-deleted rows. */
    public static function withTrashed(): \Nemesis\Core\Builder
    {
        // Temporarily remove the softDelete global scope for this query
        $builder = new \Nemesis\Core\Builder(new static());
        // Don't apply global scope — withTrashed skips it
        return $builder;
    }

    /** Return a Builder that returns ONLY soft-deleted rows. */
    public static function onlyTrashed(): \Nemesis\Core\Builder
    {
        $builder = new \Nemesis\Core\Builder(new static());
        $builder->whereNotNull('deleted_at');
        return $builder;
    }

    // -------------------------------------------------------------------------
    // State check
    // -------------------------------------------------------------------------

    public function trashed(): bool
    {
        return !is_null($this->attributes['deleted_at'] ?? null);
    }
}
