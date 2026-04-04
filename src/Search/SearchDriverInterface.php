<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 14 — Full-Text Search | Created: 2026-04-03

namespace Nemesis\Search;

/**
 * SearchDriverInterface — contract for all search backend drivers.
 */
interface SearchDriverInterface
{
    /**
     * Index a single record.
     *
     * @param  string $modelClass  FQCN of the model, e.g. 'App\Models\Post'
     * @param  int    $id          Primary key value
     * @param  array  $data        Searchable fields → values
     */
    public function index(string $modelClass, int $id, array $data): void;

    /**
     * Remove a single record from the index.
     */
    public function remove(string $modelClass, int $id): void;

    /**
     * Remove all indexed records for a model class.
     */
    public function flush(string $modelClass): void;

    /**
     * Execute a search query.
     *
     * @param  string   $term         The search term
     * @param  string[] $modelClasses Models to search within (empty = all)
     * @param  int      $limit        Max results
     * @return list<array{model:string,id:int,score:float,data:array}>
     */
    public function search(string $term, array $modelClasses = [], int $limit = 20): array;
}
