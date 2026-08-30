<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 2 — Model updates (toJson, findOrFail) | Updated: 2026-04-03

namespace Nemesis\Core;

use Nemesis\Core\Database;
use Nemesis\Support\Collection;

abstract class Model implements \ArrayAccess {
    protected $table;
    protected $primaryKey = 'id';
    protected ?string $connection = null;
    protected $attributes = [];
    protected $relations = [];
    protected static $booted = [];
    protected static $globalScopes = [];
    protected static $observers = [];
    protected $fillable = [];
    protected $guarded = ['*'];
    protected static $events = ['creating', 'created', 'updating', 'updated', 'deleting', 'deleted', 'saving', 'saved'];

    protected $original = [];

    public function __construct(array $attributes = []) {
        $this->attributes = $attributes;
        $this->syncOriginal();
        
        if (!isset(static::$booted[static::class])) {
            static::boot();
            static::$booted[static::class] = true;
        }
    }

    protected static function boot() {
        // Boot traits logic (Simplified to avoid recursion issues)
        $traits = [];
        
        // Get traits from current class and parents
        foreach (array_merge([static::class], class_parents(static::class)) as $class) {
            $traits = array_merge($traits, class_uses($class));
        }

        foreach (array_unique($traits) as $trait) {
            $method = 'boot' . class_basename($trait);
            if (method_exists(static::class, $method)) {
                static::$method();
            }
        }
    }

    // Model Events
    public static function creating($callback) { static::registerModelEvent('creating', $callback); }
    public static function created($callback) { static::registerModelEvent('created', $callback); }
    public static function updating($callback) { static::registerModelEvent('updating', $callback); }
    public static function updated($callback) { static::registerModelEvent('updated', $callback); }
    public static function deleting($callback) { static::registerModelEvent('deleting', $callback); }
    public static function deleted($callback) { static::registerModelEvent('deleted', $callback); }
    public static function saving($callback) { static::registerModelEvent('saving', $callback); }
    public static function saved($callback) { static::registerModelEvent('saved', $callback); }

    protected static function registerModelEvent($event, $callback) {
        if (!isset(static::$observers[static::class])) {
            static::$observers[static::class] = [];
        }
        // Store as [event => callback] or just mixed list?
        // Observer system expects objects. Let's wrap closure or handle mixed.
        // Simplest: Just append. fireModelEvent needs check.
        static::$observers[static::class][] = ['event' => $event, 'callback' => $callback];
    }

    protected function fireModelEvent($event) {
        $method = $event;
        
        // Call observers & registered callbacks
        if (isset(static::$observers[static::class])) {
            foreach (static::$observers[static::class] as $observer) {
                // Handle registered closures
                if (is_array($observer) && isset($observer['event']) && $observer['event'] === $event) {
                    $callback = $observer['callback'];
                    if (is_callable($callback)) {
                        if ($callback($this) === false) return false;
                    }
                    continue;
                }

                // Handle class observers
                if (is_object($observer) && method_exists($observer, $event)) {
                    if ($observer->$event($this) === false) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    public static function observe($class) {
        $observer = is_string($class) ? new $class() : $class;
        
        if (!isset(static::$observers[static::class])) {
            static::$observers[static::class] = [];
        }
        
        static::$observers[static::class][] = $observer;
    }

    // Magic getter for attributes and relationships
    public function __get($key) {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        // Try to load relationship dynamically
        if (method_exists($this, $key)) {
            return $this->getRelationValue($key);
        }

        return null;
    }

    // Magic setter
    public function __set($key, $value) {
        $this->attributes[$key] = $value;
    }

    protected function getRelationValue($key) {
        if (!array_key_exists($key, $this->relations)) {
            $this->relations[$key] = $this->$key()->get();
        }
        return $this->relations[$key];
    }

    /**
     * Set a relation value directly (used by Builder::eagerLoadRelations).
     */
    public function setRelation(string $name, mixed $value): static
    {
        $this->relations[$name] = $value;
        return $this;
    }

    public function getRelations(): array
    {
        return $this->relations;
    }

    // Static query builder
    public static function query() {
        $instance = new static();
        $builder = new Builder($instance);
        return $instance->applyGlobalScopes($builder);
    }

    public function getConnectionName(): ?string
    {
        return $this->connection !== null && $this->connection !== ''
            ? strtolower(trim($this->connection))
            : null;
    }

    public function setConnection(?string $connection)
    {
        $this->connection = $connection !== null && $connection !== ''
            ? strtolower(trim($connection))
            : null;
        return $this;
    }

    public static function addGlobalScope($scope, $implementation = null) {
        // echo "Adding Scope: $scope\n";
        if (is_string($scope) && $implementation !== null) {
            self::$globalScopes[static::class][$scope] = $implementation;
        } else {
            self::$globalScopes[static::class][spl_object_hash($scope)] = $scope;
        }
    }

    public function applyGlobalScopes($builder) {
        $scopes = static::$globalScopes[static::class] ?? [];
        // echo "Applying scopes for " . static::class . ": " . count($scopes) . "\n";
        foreach ($scopes as $identifier => $scope) {
            if (is_callable($scope)) {
                $scope($builder);
            }
        }
        return $builder;
    }

    public static function find(mixed $id): ?static
    {
        return static::query()->find($id);
    }

    /**
     * Find by primary key or throw NotFoundException.
     *
     * @throws \Nemesis\Exceptions\NotFoundException
     */
    public static function findOrFail(mixed $id): static
    {
        return static::query()->findOrFail($id);
    }

    /** Return all rows as a Collection of hydrated Models. */
    public static function all(): Collection
    {
        return static::query()->get();
    }

    public static function where(string $column, mixed $operator, mixed $value = null): Builder
    {
        return static::query()->where($column, $operator, $value);
    }

    public static function whereNull(string $column): Builder
    {
        return static::query()->whereNull($column);
    }

    public static function whereNotNull(string $column): Builder
    {
        return static::query()->whereNotNull($column);
    }

    public static function whereIn(string $column, array $values): Builder
    {
        return static::query()->whereIn($column, $values);
    }

    public static function latest(string $column = 'created_at'): Builder
    {
        return static::query()->latest($column);
    }

    public static function oldest(string $column = 'created_at'): Builder
    {
        return static::query()->oldest($column);
    }

    public static function create(array $attributes = []) {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public function getTable() {
        if (!isset($this->table)) {
            // Auto-generate table name from class name (pluralized lowercase)
            $class = class_basename(static::class);
            $this->table = strtolower($class) . 's';
        }
        return $this->table;
    }

    public function setTable($table) {
        $this->table = $table;
        return $this;
    }

    public function getKey() {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    public $exists = false;

    // Persist the model to the database
    public function save(array $options = []) {
        if ($this->fireModelEvent('saving') === false) return false;

        $query = static::query();

        if ($this->exists) {
            if ($this->fireModelEvent('updating') === false) return false;
            
            // Update timestamp
            if ($this->usesTimestamps()) {
                $this->updateTimestamps();
            }

            $query->where($this->getKeyName(), '=', $this->getKey())->update($this->attributes);
            
            $this->fireModelEvent('updated');
        } else {
            if ($this->fireModelEvent('creating') === false) return false;
            
            if ($this->usesTimestamps()) {
                $this->updateTimestamps();
            }

            $id = $query->insert($this->attributes);
            
            $this->exists = true;
            $this->attributes[$this->getKeyName()] = $id;
            
            $this->fireModelEvent('created');
        }

        $this->fireModelEvent('saved');
        $this->syncOriginal();
        return true;
    }

    public function update(array $attributes = [], array $options = []) {
        if (!$this->exists) {
            return false;
        }
        $this->fill($attributes);
        return $this->save($options);
    }

    public function delete() {
        if ($this->fireModelEvent('deleting') === false) return false;

        $query = static::query()->where($this->getKeyName(), '=', $this->getKey());
        $query->delete();

        $this->exists = false;
        $this->fireModelEvent('deleted');
        return true;
    }

    public function fill(array $attributes) {
        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this;
    }

    protected function fillableFromArray(array $attributes) {
        if (count($this->fillable) > 0 && !in_array('*', $this->fillable)) {
            return array_intersect_key($attributes, array_flip($this->fillable));
        }

        if ($this->guarded == ['*']) {
            return [];
        }

        return array_diff_key($attributes, array_flip($this->guarded));
    }

    public function setAttribute($key, $value) {
        $this->attributes[$key] = $value;
    }

    public function getKeyName() {
        return $this->primaryKey;
    }

    protected function usesTimestamps() {
        return property_exists($this, 'timestamps') ? $this->timestamps : true;
    }

    protected function updateTimestamps() {
        $time = date('Y-m-d H:i:s');
        $this->attributes['updated_at'] = $time;
        if (!$this->exists) {
            $this->attributes['created_at'] = $time;
        }
    }

    // Relationship methods
    protected function hasOne($related, $foreignKey = null, $localKey = null) {
        $instance = new $related();
        $foreignKey = $foreignKey ?: strtolower(class_basename(static::class)) . '_id';
        $localKey = $localKey ?: $this->primaryKey;

        return new HasOne($instance, $this, $foreignKey, $localKey);
    }

    protected function hasMany($related, $foreignKey = null, $localKey = null) {
        $instance = new $related();
        $foreignKey = $foreignKey ?: strtolower(class_basename(static::class)) . '_id';
        $localKey = $localKey ?: $this->primaryKey;

        return new HasMany($instance, $this, $foreignKey, $localKey);
    }

    protected function belongsTo($related, $foreignKey = null, $ownerKey = null) {
        $instance = new $related();
        $foreignKey = $foreignKey ?: strtolower(class_basename($related)) . '_id';
        $ownerKey = $ownerKey ?: $instance->primaryKey;

        return new BelongsTo($instance, $this, $foreignKey, $ownerKey);
    }

    protected function belongsToMany($related, $table = null, $foreignPivotKey = null, $relatedPivotKey = null) {
        $instance = new $related();
        
        if (!$table) {
            $models = [
                strtolower(class_basename(static::class)),
                strtolower(class_basename($related))
            ];
            sort($models);
            $table = implode('_', $models);
        }

        $foreignPivotKey = $foreignPivotKey ?: strtolower(class_basename(static::class)) . '_id';
        $relatedPivotKey = $relatedPivotKey ?: strtolower(class_basename($related)) . '_id';

        return new BelongsToMany($instance, $this, $table, $foreignPivotKey, $relatedPivotKey);
    }

    public function toArray(): array
    {
        return array_merge($this->attributes, $this->relations);
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    public function getAttributes() {
        return $this->attributes;
    }

    public function getOriginal($key = null) {
        if ($key) {
            return $this->original[$key] ?? null;
        }
        return $this->original;
    }

    public function syncOriginal() {
        $this->original = $this->attributes;
        return $this;
    }

    public function getChanges() {
        $changes = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $changes[$key] = $value;
            }
        }
        return $changes;
    }

    // Query Scopes
    public function __call($method, $parameters) {
        // Check for scope methods
        if (method_exists($this, 'scope' . ucfirst($method))) {
            return call_user_func_array([$this, 'scope' . ucfirst($method)], array_merge([static::query()], $parameters));
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }

    public static function __callStatic(string $method, array $parameters): mixed
    {
        $instance = new static();

        // Delegate to Builder first — handles whereNull, whereIn, orderBy, etc.
        if (method_exists(Builder::class, $method)) {
            return call_user_func_array([static::query(), $method], $parameters);
        }

        // Check for scope methods on the model
        if (method_exists($instance, 'scope' . ucfirst($method))) {
            return call_user_func_array([$instance, 'scope' . ucfirst($method)], array_merge([static::query()], $parameters));
        }

        throw new \BadMethodCallException("Static method [{$method}] does not exist on " . static::class . '.');
    }
 
    // ArrayAccess Implementation
    public function offsetExists($offset): bool {
        return isset($this->attributes[$offset]);
    }

    public function offsetGet($offset): mixed {
        return $this->__get($offset);
    }

    public function offsetSet($offset, $value): void {
        $this->__set($offset, $value);
    }

    public function offsetUnset($offset): void {
        unset($this->attributes[$offset]);
    }
}

// class_basename() moved to src/Helpers/Helpers.php — 2026-04-02 (R3 fix)
