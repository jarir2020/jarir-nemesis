<?php

namespace Nemesis\Core\Traits;

trait SoftDeletes {
    protected static $forceDeleting = false;

    protected function initializeSoftDeletes() {
        // Auto-add deleted_at to dates if using timestamps
    }

    public static function bootSoftDeletes() {
        // Add global scope to exclude soft deleted records
        static::addGlobalScope('softDelete', function($builder) {
            if (!static::$forceDeleting) {
                $builder->whereNull('deleted_at');
            }
        });
    }

    public function delete() {
        if (static::$forceDeleting) {
            return $this->forceDelete();
        }

        $this->runModelEvent('deleting');
        
        // Soft delete: set deleted_at timestamp
        Fluent::table($this->getTable())
              ->where($this->primaryKey, '=', $this->getKey())
              ->update(['deleted_at' => date('Y-m-d H:i:s')]);
        
        $this->attributes['deleted_at'] = date('Y-m-d H:i:s');
        $this->runModelEvent('deleted');
        
        return true;
    }

    public function forceDelete() {
        $this->runModelEvent('forceDeleting');
        
        Fluent::table($this->getTable())
              ->where($this->primaryKey, '=', $this->getKey())
              ->delete();
        
        $this->runModelEvent('forceDeleted');
        
        return true;
    }

    public function restore() {
        $this->runModelEvent('restoring');
        
        Fluent::table($this->getTable())
              ->where($this->primaryKey, '=', $this->getKey())
              ->update(['deleted_at' => null]);
        
        $this->attributes['deleted_at'] = null;
        $this->runModelEvent('restored');
        
        return $this;
    }

    public static function withTrashed() {
        static::$forceDeleting = true;
        return static::query();
    }

    public static function onlyTrashed() {
        return static::query()->whereNotNull('deleted_at');
    }

    public function trashed() {
        return !is_null($this->attributes['deleted_at'] ?? null);
    }

    // Placeholder for event runner (will be implemented in Model class)
    protected function runModelEvent($event) {
        if (method_exists($this, 'fireModelEvent')) {
            return $this->fireModelEvent($event);
        }
    }
}
