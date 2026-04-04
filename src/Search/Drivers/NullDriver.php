<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 14 — Full-Text Search | Created: 2026-04-03

namespace Nemesis\Search\Drivers;

use Nemesis\Search\SearchDriverInterface;

/**
 * NullDriver — in-memory search driver.
 * Perfect for unit tests and environments without a real search backend.
 * Uses simple substring matching (case-insensitive) across all indexed values.
 */
class NullDriver implements SearchDriverInterface
{
    /** @var array<string, array<int, array>>  [modelClass][id] = data */
    private array $index = [];

    public function index(string $modelClass, int $id, array $data): void
    {
        $this->index[$modelClass][$id] = $data;
    }

    public function remove(string $modelClass, int $id): void
    {
        unset($this->index[$modelClass][$id]);
    }

    public function flush(string $modelClass): void
    {
        unset($this->index[$modelClass]);
    }

    public function search(string $term, array $modelClasses = [], int $limit = 20): array
    {
        $results = [];
        $needle  = mb_strtolower($term);
        $scope   = empty($modelClasses) ? array_keys($this->index) : $modelClasses;

        foreach ($scope as $modelClass) {
            foreach ($this->index[$modelClass] ?? [] as $id => $data) {
                $haystack = mb_strtolower(implode(' ', array_map('strval', array_values($data))));
                if (str_contains($haystack, $needle)) {
                    // Naive score: count of occurrences
                    $score = substr_count($haystack, $needle);
                    $results[] = [
                        'model' => $modelClass,
                        'id'    => $id,
                        'score' => (float) $score,
                        'data'  => $data,
                    ];
                }
            }
        }

        // Sort by score descending
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    /** Return all indexed entries (test helper). */
    public function all(): array { return $this->index; }

    /** Flush entire index. */
    public function reset(): void { $this->index = []; }
}
