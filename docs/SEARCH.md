# Full-Text Search

Nemesis provides a driver-based full-text search system with a fluent query builder. Three drivers are included: in-memory (for tests), database LIKE, and MeiliSearch.

---

## Quick Start

```php
use Nemesis\Search\SearchEngine;

// Index a document
SearchEngine::index('App\Models\Post', 1, [
    'title'   => 'Getting Started with Nemesis',
    'body'    => 'A quick introduction to the framework...',
    'tags'    => 'php framework beginner',
]);

// Search
$results = SearchEngine::search('nemesis framework')
    ->in('App\Models\Post')
    ->limit(10)
    ->get();
```

---

## Searchable Trait

Add `Searchable` to any Model for automatic indexing.

```php
use Nemesis\Core\Model;
use Nemesis\Search\Searchable;

class Post extends Model
{
    use Searchable;
    protected string $table = 'posts';

    // Optional: customise which fields go into the index
    public function toSearchArray(): array
    {
        return [
            'title'   => $this->title,
            'excerpt' => $this->excerpt,
            'tags'    => implode(' ', $this->tags ?? []),
        ];
    }
}
```

### Index & Remove

```php
$post = Post::find(1);

$post->searchIndex();   // add/update in search index
$post->searchRemove();  // remove from index
Post::flushSearchIndex(); // wipe all Post documents from index
```

### Search via the Model

```php
$results = Post::search('nemesis framework')
    ->limit(20)
    ->get();
```

---

## SearchQuery Fluent Builder

`SearchEngine::search()` returns a `SearchQuery` that you refine before executing.

```php
$query = SearchEngine::search('open source php');

// Restrict to specific models
$query->in('App\Models\Post')
      ->in('App\Models\Page');

// Limit results
$query->limit(25);

// Post-filter on metadata returned by the driver
$query->where('status', 'published')
      ->where('language', 'en');

// Execute
$all   = $query->get();    // array of result arrays
$first = $query->first();  // first result or null
$count = $query->count();  // int
```

---

## Drivers

### NullDriver (in-memory, default)

Used in tests and when no DB/external engine is configured. Stores documents in-memory; searches case-insensitively using `str_contains`. Scores by substring frequency.

```php
use Nemesis\Search\SearchEngine;
use Nemesis\Search\Drivers\NullDriver;

SearchEngine::setDriverInstance(new NullDriver());
```

### DatabaseDriver

Stores documents in a `search_indexes` table (auto-created). Uses SQL `LIKE '%term%'` queries. Falls back to `NullDriver` when no DB connection is available.

```php
use Nemesis\Search\Drivers\DatabaseDriver;

SearchEngine::setDriverInstance(new DatabaseDriver());

// Or set via driver name (reads DB config automatically)
SearchEngine::setDriver('database');
```

### MeiliSearchDriver

Full-featured search via [MeiliSearch](https://www.meilisearch.com/). Communicates over the REST API. Falls back to `NullDriver` when MeiliSearch is unreachable.

```php
SearchEngine::setDriver('meilisearch');
```

Configure in `.env`:

```dotenv
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=your-master-key
```

Or in `config/search.php`:

```php
return [
    'driver' => env('SEARCH_DRIVER', 'null'),  // null | database | meilisearch
    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key'  => env('MEILISEARCH_KEY', ''),
    ],
];
```

Index names are derived from the model class: `App\Models\BlogPost` → `app_models_blogpost`.

---

## SearchEngine Facade

```php
use Nemesis\Search\SearchEngine;

// Index a document
SearchEngine::index(string $modelClass, int|string $id, array $data): void

// Remove a document
SearchEngine::remove(string $modelClass, int|string $id): void

// Flush all documents for a model
SearchEngine::flush(string $modelClass): void

// Start a query
SearchEngine::search(string $term): SearchQuery

// Low-level query (returns raw driver results)
SearchEngine::query(string $term, string $modelClass, int $limit): array

// Swap driver at runtime
SearchEngine::setDriver('meilisearch');
SearchEngine::setDriverInstance(new CustomDriver());
SearchEngine::getDriver();   // returns current SearchDriverInterface
```

---

## Custom Driver

Implement `SearchDriverInterface`:

```php
use Nemesis\Search\SearchDriverInterface;

class ElasticsearchDriver implements SearchDriverInterface
{
    public function index(string $modelClass, int|string $id, array $data): void
    {
        // POST to Elasticsearch
    }

    public function remove(string $modelClass, int|string $id): void
    {
        // DELETE from Elasticsearch
    }

    public function flush(string $modelClass): void
    {
        // Delete all documents for the index
    }

    public function search(string $term, string $modelClass, int $limit = 20): array
    {
        // Returns: [['id'=>1, 'score'=>1.5, ...], ...]
    }
}

SearchEngine::setDriverInstance(new ElasticsearchDriver());
```

---

## Example: Auto-Index on Save

Use a Model observer or the `RecordsActivity` trait to keep the index fresh:

```php
class Post extends Model
{
    use Searchable;

    public function save(): bool
    {
        $result = parent::save();
        if ($result) {
            $this->searchIndex();
        }
        return $result;
    }

    public function delete(): bool
    {
        $this->searchRemove();
        return parent::delete();
    }
}
```
