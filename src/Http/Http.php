<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 19 — HTTP Client: Http facade | 2026-04-06

namespace Nemesis\Http;

use Nemesis\Http\Client\HttpClient;
use Nemesis\Http\Client\HttpResponse;
use Nemesis\Http\Client\HttpResponseException;

/**
 * Http — static facade for the fluent HTTP client.
 *
 * Basic usage:
 *   $response = Http::get('https://api.example.com/users');
 *   $response = Http::post('https://api.example.com/users', ['name' => 'Alice']);
 *
 * Fluent builder:
 *   $response = Http::withToken('my-token')
 *                   ->timeout(10)
 *                   ->retry(3, 200)
 *                   ->acceptJson()
 *                   ->get('https://api.example.com/users');
 *
 * Testing (fake responses, no real HTTP):
 *   Http::fake([
 *       'https://api.example.com/*' => Http::response(['data' => []], 200),
 *       'https://other.example.com' => Http::response('Not Found', 404),
 *   ]);
 *
 *   $response = Http::get('https://api.example.com/users');
 *   $response->json('data'); // []
 *
 *   Http::assertSent(fn($req) => $req['url'] === 'https://api.example.com/users');
 *   Http::resetFakes();
 *
 * Concurrent pool:
 *   $responses = Http::pool(fn($pool) => [
 *       $pool->get('https://api.example.com/users'),
 *       $pool->get('https://api.example.com/posts'),
 *   ]);
 */
class Http
{
    // -------------------------------------------------------------------------
    // Fake registry (test support)
    // -------------------------------------------------------------------------

    /** @var array<string, array> URL-pattern → response descriptor */
    private static array $fakes    = [];
    /** @var list<array{method:string,url:string,data:array}> */
    private static array $recorded = [];
    private static bool  $faking   = false;

    /**
     * Register fake responses for testing.
     * Keys are URL patterns supporting '*' wildcards.
     *
     * @param array<string, array> $responses  Use Http::response() to build descriptors
     */
    public static function fake(array $responses = []): void
    {
        self::$fakes    = $responses;
        self::$recorded = [];
        self::$faking   = true;
    }

    /**
     * Build a fake response descriptor.
     *
     * @param mixed $body    Array (JSON), string, or null
     * @param int   $status  HTTP status code
     * @param array $headers Response headers
     */
    public static function response(mixed $body = null, int $status = 200, array $headers = []): array
    {
        return ['_fake' => true, 'body' => $body, 'status' => $status, 'headers' => $headers];
    }

    /** @internal Called by HttpClient before sending. */
    public static function recordRequest(string $method, string $url, array $data = []): void
    {
        self::$recorded[] = ['method' => strtoupper($method), 'url' => $url, 'data' => $data];
    }

    /** @internal */
    public static function isFaking(): bool { return self::$faking; }

    /**
     * @internal Match URL against registered fake patterns.
     * Returns the fake descriptor, or null if no pattern matched.
     */
    public static function matchFake(string $url): ?array
    {
        foreach (self::$fakes as $pattern => $descriptor) {
            if ($pattern === '*'
                || $pattern === $url
                || fnmatch($pattern, $url)
                || str_starts_with($url, rtrim($pattern, '*'))
            ) {
                return $descriptor;
            }
        }
        // Faking is active but URL has no explicit mapping — return 200 empty
        return self::$faking ? self::response([], 200) : null;
    }

    // -------------------------------------------------------------------------
    // Assertions (use in tests after Http::fake())
    // -------------------------------------------------------------------------

    /**
     * Assert that at least one recorded request matches the callback.
     *
     * @param callable(array{method:string,url:string,data:array}):bool $callback
     * @throws \RuntimeException
     */
    public static function assertSent(callable $callback): void
    {
        foreach (self::$recorded as $request) {
            if ($callback($request) === true) return;
        }
        throw new \RuntimeException(
            'The expected HTTP request was not sent. Recorded: ' . self::describeRecorded()
        );
    }

    /**
     * Assert that no recorded request matches the callback.
     * @throws \RuntimeException
     */
    public static function assertNotSent(callable $callback): void
    {
        foreach (self::$recorded as $request) {
            if ($callback($request) === true) {
                throw new \RuntimeException(
                    'An unexpected HTTP request was sent: ' . json_encode($request)
                );
            }
        }
    }

    /**
     * Assert that no HTTP requests were made at all.
     * @throws \RuntimeException
     */
    public static function assertNothingSent(): void
    {
        if (!empty(self::$recorded)) {
            throw new \RuntimeException(
                'Expected no HTTP requests, but ' . count(self::$recorded) . ' were sent: '
                . self::describeRecorded()
            );
        }
    }

    /**
     * Assert a specific number of requests were recorded.
     * @throws \RuntimeException
     */
    public static function assertSentCount(int $count): void
    {
        $actual = count(self::$recorded);
        if ($actual !== $count) {
            throw new \RuntimeException(
                "Expected {$count} HTTP requests, but {$actual} were sent."
            );
        }
    }

    /** All recorded requests. Useful for custom assertions. */
    public static function recorded(): array { return self::$recorded; }

    /** Reset all fakes and recorded requests. Call in tearDown(). */
    public static function resetFakes(): void
    {
        self::$fakes    = [];
        self::$recorded = [];
        self::$faking   = false;
    }

    private static function describeRecorded(): string
    {
        if (empty(self::$recorded)) return '(none)';
        return implode(', ', array_map(
            fn($r) => "{$r['method']} {$r['url']}",
            self::$recorded
        ));
    }

    // -------------------------------------------------------------------------
    // Pool (concurrent requests)
    // -------------------------------------------------------------------------

    /**
     * Run multiple requests concurrently.
     *
     * @return list<HttpResponse>
     */
    public static function pool(callable $callback): array
    {
        return HttpClient::pool($callback);
    }

    // -------------------------------------------------------------------------
    // Static facade — proxy all HttpClient methods
    // -------------------------------------------------------------------------

    /**
     * Proxy to a fresh HttpClient instance.
     * Supports all fluent builder methods:
     *   Http::withToken(), Http::withHeaders(), Http::timeout(), Http::retry(),
     *   Http::asJson(), Http::asForm(), Http::acceptJson(),
     *   Http::get(), Http::post(), Http::put(), Http::patch(), Http::delete()
     *
     * @return HttpClient|HttpResponse
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        return (new HttpClient())->$method(...$args);
    }
}
