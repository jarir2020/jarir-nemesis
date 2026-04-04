<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 14 — Full-Text Search | Created: 2026-04-03

namespace Nemesis\Search;

use Nemesis\Search\Drivers\NullDriver;
use Nemesis\Search\Drivers\DatabaseDriver;
use Nemesis\Search\Drivers\MeiliSearchDriver;

/**
 * SearchEngine — unified facade for full-text search.
 *
 * Usage:
 *   // Configure driver (once at boot)
 *   SearchEngine::setDriver('database');
 *   // or with custom instance:
 *   SearchEngine::setDriverInstance(new MeiliSearchDriver($host, $key));
 *
 *   // Index a record
 *   SearchEngine::index(Post::class, 42, ['title' => 'Hello World', 'body' => '...']);
 *
 *   // Fluent search
 *   $results = SearchEngine::query('hello world')
 *       ->in([Post::class, Page::class])
 *       ->limit(10)
 *       ->get();
 *
 *   // Remove / flush
 *   SearchEngine::remove(Post::class, 42);
 *   SearchEngine::flush(Post::class);
 */
class SearchEngine
{
    private static ?SearchDriverInterface $driver = null;
    private static string $defaultDriver = 'null';

    // -------------------------------------------------------------------------
    // Driver management
    // -------------------------------------------------------------------------

    /**
     * Set the active driver by name.
     * Supported: 'null', 'database', 'meilisearch'
     */
    public static function setDriver(string $name): void
    {
        static::$defaultDriver = $name;
        static::$driver = match (strtolower($name)) {
            'database'    => new DatabaseDriver(),
            'meilisearch' => new MeiliSearchDriver(),
            default       => new NullDriver(),
        };
    }

    /** Inject a custom driver instance. */
    public static function setDriverInstance(SearchDriverInterface $driver): void
    {
        static::$driver = $driver;
    }

    /** Return the active driver (lazy-initialised to NullDriver). */
    public static function getDriver(): SearchDriverInterface
    {
        if (static::$driver === null) {
            static::$driver = new NullDriver();
        }
        return static::$driver;
    }

    /** Reset to default state (test helper). */
    public static function reset(): void
    {
        static::$driver        = null;
        static::$defaultDriver = 'null';
    }

    // -------------------------------------------------------------------------
    // Index operations
    // -------------------------------------------------------------------------

    /**
     * Index a single record.
     *
     * @param  string $modelClass  FQCN of the model
     * @param  int    $id          Primary key
     * @param  array  $data        Searchable fields
     */
    public static function index(string $modelClass, int $id, array $data): void
    {
        static::getDriver()->index($modelClass, $id, $data);
    }

    /**
     * Remove a single record from the index.
     */
    public static function remove(string $modelClass, int $id): void
    {
        static::getDriver()->remove($modelClass, $id);
    }

    /**
     * Clear all indexed records for a model.
     */
    public static function flush(string $modelClass): void
    {
        static::getDriver()->flush($modelClass);
    }

    // -------------------------------------------------------------------------
    // Search
    // -------------------------------------------------------------------------

    /**
     * Begin a fluent search query.
     *
     * @param  string $term  The search term
     */
    public static function query(string $term): SearchQuery
    {
        return new SearchQuery($term);
    }

    /**
     * Convenience: execute a simple search immediately.
     *
     * @return list<array{model:string,id:int,score:float,data:array}>
     */
    public static function search(string $term, array $models = [], int $limit = 20): array
    {
        return static::query($term)->in($models)->limit($limit)->get();
    }
}
