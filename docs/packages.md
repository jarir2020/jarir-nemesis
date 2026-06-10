# Bundled packages

Every `jarir-ahmed/*` package ships with Nemesis (in `vendor/`) but stays
**dormant until you use it** — nothing is constructed at boot. The ones that
expose a service register themselves automatically through Nemesis's package
auto-discovery (`extra.nemesis.providers` in each package's `composer.json`,
collected by `Nemesis\Core\PackageManifest` and registered in `index.php`).

Resolve a service from the container whenever you need it:

```php
$cache  = app('cache');        // jarir-ahmed/cache
$llm    = app('llm');          // jarir-ahmed/php-llm
$search = app('search.web');   // jarir-ahmed/uncensored-search
```

> `app($id)` is shorthand for `Nemesis\Core\Container::getInstance()->make($id)`.

---

## Cache — `app('cache')`

Returns a `JarirAhmed\Cache\CacheManager`. Stores: array, file, apcu, redis,
memcached, pdo, tiered. Also PSR-16 / PSR-6.

```php
$cache = app('cache');

$cache->store()->set('key', 'value', 60);          // default store
$cache->store('redis')->remember('users', 300, fn () => loadUsers());
$cache->store('array')->increment('hits');

// Tags + locks + stampede protection
$cache->store()->tags(['users'])->flush();
$cache->store()->lock('import', 30)->get(fn () => runOnce());
$report = $cache->store()->rememberLocked('daily', 600, fn () => build());
```

**Config:** reads `config/cache.php`. The default store and Redis host come from
`CACHE_DRIVER` / `REDIS_HOST` / `REDIS_PORT` in `.env`. With no config it falls
back to an in-memory array store.

---

## LLM — `app('llm')`

Returns a `JarirAhmed\PhpLlm\AIClient` (OpenAI, Anthropic, Gemini, Groq,
DeepSeek, Mistral, Cohere, Grok, OpenRouter, Azure, Ollama).

```php
$llm = app('llm');

echo $llm->ask('Explain dependency injection in one line.');

$res = $llm->chat()->provider('groq')->system('Be terse.')
    ->message('Capital of France?')->chat();
echo $res['content'];                 // "Paris."
echo $res['usage']['total_tokens'];   // token count
echo '$' . $res['cost'];              // estimated USD
```

**Config:** set a default provider + its key in `.env`
(`AI_DEFAULT_LLM`, `OPENAI_API_KEY`, `GROQ_API_KEY`, …). For full control create
`config/llm.php` returning the package config array (see php-llm README); the
provider reads it via `config('llm')`.

---

## Web search — `app('search.web')`

Returns a `JarirAhmed\UncensoredSearch\UncensoredSearch` meta-search client
(Serper, Tavily, SearchApi, Scavio, Firecrawl, ScrapingBee) with failover.

```php
$results = app('search.web')->search('open source vector database');

foreach ($results as $hit) {
    echo $hit->title() . ' — ' . $hit->url() . PHP_EOL;
}
```

**Config:** set any provider key(s) in `.env` (`SERPER_API_KEY`, `TAVILY_API_KEY`,
…). Or define `config/search.php` with a `keys` array:

```php
// config/search.php
return [
    'keys' => [
        'serper' => env('SERPER_API_KEY'),
        'tavily' => env('TAVILY_API_KEY'),
    ],
];
```

With no keys set, `app('search.web')` still resolves — searches just return an
empty result set (it never fatals at resolve).

---

## The other bundled packages

These are plain libraries — `use` the class directly, no container entry needed:

| Package | Use |
|---------|-----|
| `jarir-ahmed/http-response` | JSON / status / redirect responses |
| `jarir-ahmed/server-stats` | counters, gauges, timers, system metrics |
| `jarir-ahmed/file` | filesystem helpers |
| `jarir-ahmed/form-generator` | build/validate HTML forms |
| `jarir-ahmed/hash-helper` | hashing helpers |
| `jarir-ahmed/password-generator` | password generation |
| `jarir-ahmed/auth-token-maker` | tokens / TOTP |
| `jarir-ahmed/data-encryption-utility` | encrypt/decrypt payloads |
| `jarir-ahmed/registration-data-checker` | registration validation |
| `jarir-ahmed/notification-system` | notifications (mail/Slack) |
| `jarir-ahmed/time-helper` | date/time helpers |
| `jarir-ahmed/universal-cors` | CORS handling |
| `jarir-ahmed/universal-spa` | SPA serving/fallback |
| `jarir-ahmed/user-info-capture` | request/user metadata capture |
| `jarir-ahmed/search` | indexed search (Meilisearch/Typesense) |

---

## Adding auto-discovery to another package

Any package can expose a container service the same way:

1. Add to the package's `composer.json`:
   ```json
   "extra": { "nemesis": { "providers": ["Vendor\\Pkg\\NemesisServiceProvider"] } }
   ```
2. Ship a provider extending `Nemesis\Core\ServiceProvider`:
   ```php
   class NemesisServiceProvider extends \Nemesis\Core\ServiceProvider
   {
       public function register()
       {
           // lazy: closure runs only when app('thing') is resolved
           $this->container->singleton('thing', fn () => new \Vendor\Pkg\Thing(
               \Nemesis\Core\Config::get('thing', [])
           ));
       }
   }
   ```

On the next request `PackageManifest` discovers it (cache rebuilds whenever
`composer install/update` changes `vendor/composer/installed.json`). To force a
rebuild, delete `storage/framework/packages.php`.
