<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 14 — Full-Text Search | Created: 2026-04-03

namespace Nemesis\Search\Drivers;

use Nemesis\Search\SearchDriverInterface;

/**
 * MeiliSearchDriver — search backend using the MeiliSearch REST API.
 *
 * Configuration (config/search.php or env):
 *   MEILISEARCH_HOST=http://127.0.0.1:7700
 *   MEILISEARCH_KEY=masterKey
 *
 * Falls back to NullDriver if MeiliSearch is unreachable.
 *
 * @see https://docs.meilisearch.com/reference/api/
 */
class MeiliSearchDriver implements SearchDriverInterface
{
    private string $host;
    private string $key;
    private NullDriver $fallback;

    public function __construct(string $host = '', string $key = '')
    {
        $this->host     = rtrim($host ?: (string) getenv('MEILISEARCH_HOST') ?: 'http://127.0.0.1:7700', '/');
        $this->key      = $key  ?: (string) getenv('MEILISEARCH_KEY') ?: '';
        $this->fallback = new NullDriver();
    }

    // -------------------------------------------------------------------------
    // Interface implementation
    // -------------------------------------------------------------------------

    public function index(string $modelClass, int $id, array $data): void
    {
        $this->fallback->index($modelClass, $id, $data);

        $indexName = $this->indexName($modelClass);
        $document  = array_merge(['id' => $id], $data);

        $this->request('POST', "/indexes/{$indexName}/documents", [$document]);
    }

    public function remove(string $modelClass, int $id): void
    {
        $this->fallback->remove($modelClass, $id);

        $indexName = $this->indexName($modelClass);
        $this->request('DELETE', "/indexes/{$indexName}/documents/{$id}");
    }

    public function flush(string $modelClass): void
    {
        $this->fallback->flush($modelClass);

        $indexName = $this->indexName($modelClass);
        $this->request('DELETE', "/indexes/{$indexName}/documents");
    }

    public function search(string $term, array $modelClasses = [], int $limit = 20): array
    {
        $results = [];
        $scope   = empty($modelClasses) ? [$this->indexName('*')] : array_map([$this,'indexName'], $modelClasses);

        foreach ($scope as $i => $indexName) {
            $modelClass = $modelClasses[$i] ?? $indexName;
            $response   = $this->request('POST', "/indexes/{$indexName}/search", [
                'q'     => $term,
                'limit' => $limit,
            ]);

            if ($response === null) {
                // Fallback to in-memory for this index
                $fallbackResults = $this->fallback->search($term, [$modelClass], $limit);
                $results = array_merge($results, $fallbackResults);
                continue;
            }

            foreach ($response['hits'] ?? [] as $hit) {
                $id = (int) ($hit['id'] ?? 0);
                unset($hit['id']);
                $results[] = [
                    'model' => $modelClass,
                    'id'    => $id,
                    'score' => (float) ($hit['_rankingScore'] ?? 1.0),
                    'data'  => $hit,
                ];
            }
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($results, 0, $limit);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Convert a FQCN to a safe MeiliSearch index name (lowercase, underscores). */
    private function indexName(string $modelClass): string
    {
        return strtolower(str_replace(['\\', '/'], '_', ltrim($modelClass, '\\')));
    }

    /**
     * Make an HTTP request to MeiliSearch.
     * Returns decoded JSON on success, null on failure.
     */
    private function request(string $method, string $path, array $body = []): ?array
    {
        $url  = $this->host . $path;
        $json = $body ? json_encode($body, JSON_UNESCAPED_UNICODE) : null;

        $headers = ["Content-Type: application/json"];
        if ($this->key) {
            $headers[] = "Authorization: Bearer {$this->key}";
        }
        if ($json) {
            $headers[] = "Content-Length: " . strlen($json);
        }

        $opts = [
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headers) . "\r\n",
                'content'       => $json,
                'timeout'       => 3,
                'ignore_errors' => true,
            ],
        ];

        $ctx      = stream_context_create($opts);
        $response = @file_get_contents($url, false, $ctx);

        if ($response === false) return null;

        try {
            return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
    }
}
