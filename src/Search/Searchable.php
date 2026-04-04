<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 14 — Full-Text Search | Created: 2026-04-03

namespace Nemesis\Search;

/**
 * Searchable — add to any Model to make it automatically indexed for full-text search.
 *
 * Usage:
 *   class Post extends Model {
 *       use Searchable;
 *
 *       // Override to define which fields get indexed
 *       public function toSearchArray(): array {
 *           return ['title' => $this->title, 'body' => $this->body];
 *       }
 *   }
 *
 *   // Manual indexing
 *   $post->searchIndex();
 *   $post->searchRemove();
 *
 *   // Search (static)
 *   Post::search('hello world');  // → SearchQuery scoped to Post
 */
trait Searchable
{
    // -------------------------------------------------------------------------
    // Boot — hook into Model save/delete lifecycle
    // -------------------------------------------------------------------------

    public static function bootSearchable(): void
    {
        // If the Model supports observers, register index/remove on save/delete.
        // This is a best-effort hook; not all Nemesis Model versions support this.
        if (method_exists(static::class, 'observe') || method_exists(static::class, 'saved')) {
            try {
                static::saved(fn(self $m) => $m->searchIndex());
                static::deleted(fn(self $m) => $m->searchRemove());
            } catch (\Throwable) {}
        }
    }

    // -------------------------------------------------------------------------
    // Instance API
    // -------------------------------------------------------------------------

    /**
     * (Re-)index this model in the search engine.
     */
    public function searchIndex(): void
    {
        $id = $this->getSearchableKey();
        if ($id === null) return;

        SearchEngine::index(static::class, $id, $this->toSearchArray());
    }

    /**
     * Remove this model from the search index.
     */
    public function searchRemove(): void
    {
        $id = $this->getSearchableKey();
        if ($id === null) return;

        SearchEngine::remove(static::class, $id);
    }

    // -------------------------------------------------------------------------
    // Static API
    // -------------------------------------------------------------------------

    /**
     * Return a SearchQuery scoped to this model class.
     *
     * @param  string $term  The search term
     */
    public static function search(string $term): SearchQuery
    {
        return SearchEngine::query($term)->in([static::class]);
    }

    /**
     * Flush all search index entries for this model.
     */
    public static function flushSearchIndex(): void
    {
        SearchEngine::flush(static::class);
    }

    // -------------------------------------------------------------------------
    // Customisation hooks — override in model
    // -------------------------------------------------------------------------

    /**
     * Return the fields that should be indexed.
     * Override to control which data is searchable.
     *
     * @return array<string, scalar>
     */
    public function toSearchArray(): array
    {
        // Fallback: use all attributes if available (Nemesis Model pattern)
        if (property_exists($this, 'attributes')) {
            $attrs = (fn() => $this->attributes)->call($this);
            // Exclude binary/large fields by default
            return array_filter(
                $attrs,
                fn($v) => is_scalar($v) && !is_null($v)
            );
        }

        if (method_exists($this, 'toArray')) {
            return array_filter((array) $this->toArray(), fn($v) => is_scalar($v));
        }

        return [];
    }

    /**
     * Return the primary key value to use as the search ID.
     */
    protected function getSearchableKey(): ?int
    {
        // Try Nemesis Model primaryKey / getAttribute
        if (property_exists($this, 'primaryKey')) {
            $pk = (fn() => $this->primaryKey)->call($this);
            if (method_exists($this, 'getAttribute')) {
                return (int) $this->getAttribute($pk) ?: null;
            }
        }
        $id = $this->id ?? null;
        return $id !== null ? (int) $id : null;
    }
}
