<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 14 — Full-Text Search | Created: 2026-04-03

namespace Nemesis\Search;

/**
 * SearchQuery — fluent builder for executing search requests.
 *
 * Usage:
 *   $results = SearchEngine::query('php framework')
 *       ->in([\App\Models\Post::class, \App\Models\Page::class])
 *       ->limit(10)
 *       ->get();
 */
class SearchQuery
{
    private string $term;
    private array  $models = [];
    private int    $limit  = 20;
    private array  $filters = [];

    public function __construct(string $term)
    {
        $this->term = $term;
    }

    // -------------------------------------------------------------------------
    // Fluent API
    // -------------------------------------------------------------------------

    /**
     * Restrict search to specific model classes.
     *
     * @param  string[] $classes  e.g. [Post::class, Page::class]
     */
    public function in(array $classes): static
    {
        $this->models = $classes;
        return $this;
    }

    /**
     * Set the maximum number of results.
     */
    public function limit(int $n): static
    {
        $this->limit = max(1, $n);
        return $this;
    }

    /**
     * Add a filter constraint (passed to driver where supported).
     */
    public function where(string $field, mixed $value): static
    {
        $this->filters[$field] = $value;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Execution
    // -------------------------------------------------------------------------

    /**
     * Execute the search and return results.
     *
     * @return list<array{model:string,id:int,score:float,data:array}>
     */
    public function get(): array
    {
        $results = SearchEngine::getDriver()->search($this->term, $this->models, $this->limit);

        // Apply in-memory filters (for drivers that don't support them natively)
        if (!empty($this->filters)) {
            $results = array_values(array_filter($results, function (array $r): bool {
                foreach ($this->filters as $field => $value) {
                    if (($r['data'][$field] ?? null) != $value) return false;
                }
                return true;
            }));
        }

        return $results;
    }

    /**
     * Execute and return only the first result, or null.
     */
    public function first(): ?array
    {
        $results = $this->limit(1)->get();
        return $results[0] ?? null;
    }

    /**
     * Execute and return the total count of matching results.
     * Note: limit is ignored for counting.
     */
    public function count(): int
    {
        $clone        = clone $this;
        $clone->limit = PHP_INT_MAX;
        return count($clone->get());
    }

    // -------------------------------------------------------------------------
    // Getters (for introspection / testing)
    // -------------------------------------------------------------------------

    public function getTerm(): string    { return $this->term; }
    public function getModels(): array   { return $this->models; }
    public function getLimit(): int      { return $this->limit; }
    public function getFilters(): array  { return $this->filters; }
}
