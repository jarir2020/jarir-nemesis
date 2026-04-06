<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 19 — HTTP Client: HttpClient (PendingRequest) | 2026-04-06

namespace Nemesis\Http\Client;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Utils as PromiseUtils;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Nemesis\Http\Http;

/**
 * HttpClient — fluent HTTP request builder.
 *
 * Usage:
 *   $response = (new HttpClient())
 *       ->withToken('my-token')
 *       ->timeout(10)
 *       ->retry(3, 200)
 *       ->acceptJson()
 *       ->get('https://api.example.com/users');
 *
 * Via static facade:
 *   Http::withToken('...')->get('https://...');
 *   Http::post('https://...', ['name' => 'Alice']);
 */
class HttpClient
{
    // Builder state
    private array   $headers     = [];
    private int     $timeout     = 30;
    private int     $retries     = 0;
    private int     $retryDelay  = 100;   // milliseconds between retries
    private string  $bodyFormat  = 'json'; // json | form | multipart | raw
    private ?string $rawBody     = null;
    private ?string $rawBodyType = null;
    private array   $attachments = [];
    private array   $guzzleOpts  = [];
    private bool    $isAsync     = false;
    private bool    $throwOnFailure = false;

    // -------------------------------------------------------------------------
    // Builder — Headers
    // -------------------------------------------------------------------------

    /** Merge extra request headers. */
    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /** Set Authorization: Bearer <token> (or custom type). */
    public function withToken(string $token, string $type = 'Bearer'): static
    {
        return $this->withHeaders(['Authorization' => "{$type} {$token}"]);
    }

    /** Set HTTP Basic Auth. */
    public function withBasicAuth(string $username, string $password): static
    {
        $this->guzzleOpts['auth'] = [$username, $password];
        return $this;
    }

    /** Set HTTP Digest Auth. */
    public function withDigestAuth(string $username, string $password): static
    {
        $this->guzzleOpts['auth'] = [$username, $password, 'digest'];
        return $this;
    }

    /** Set the Accept header. */
    public function accept(string $contentType): static
    {
        return $this->withHeaders(['Accept' => $contentType]);
    }

    /** Accept: application/json */
    public function acceptJson(): static
    {
        return $this->accept('application/json');
    }

    // -------------------------------------------------------------------------
    // Builder — Body format
    // -------------------------------------------------------------------------

    /** Send body as JSON (default). */
    public function asJson(): static
    {
        $this->bodyFormat = 'json';
        return $this;
    }

    /** Send body as application/x-www-form-urlencoded. */
    public function asForm(): static
    {
        $this->bodyFormat = 'form';
        return $this;
    }

    /** Send multipart/form-data (used with attach()). */
    public function asMultipart(): static
    {
        $this->bodyFormat = 'multipart';
        return $this;
    }

    /**
     * Send a raw body string.
     *
     * @param string $content     Raw body content
     * @param string $contentType Content-Type header value
     */
    public function withBody(string $content, string $contentType = 'application/octet-stream'): static
    {
        $this->rawBody     = $content;
        $this->rawBodyType = $contentType;
        $this->bodyFormat  = 'raw';
        return $this;
    }

    /**
     * Attach a file part for multipart upload.
     *
     * @param string $name     Form field name
     * @param string $contents File contents (use file_get_contents())
     * @param string $filename Filename sent to server
     * @param array  $headers  Extra part headers
     */
    public function attach(string $name, string $contents, string $filename = '', array $headers = []): static
    {
        $this->bodyFormat  = 'multipart';
        $this->attachments[] = array_filter([
            'name'     => $name,
            'contents' => $contents,
            'filename' => $filename ?: $name,
            'headers'  => $headers ?: [],
        ]);
        return $this;
    }

    // -------------------------------------------------------------------------
    // Builder — Behaviour
    // -------------------------------------------------------------------------

    /** Request timeout in seconds. */
    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Retry on failure.
     *
     * @param int $times    Maximum number of attempts (not counting the first)
     * @param int $sleepMs  Milliseconds to wait between retries (0 = no wait)
     */
    public function retry(int $times, int $sleepMs = 0): static
    {
        $this->retries     = $times;
        $this->retryDelay  = $sleepMs;
        return $this;
    }

    /** Pass raw Guzzle options (merged with generated ones). */
    public function withOptions(array $options): static
    {
        $this->guzzleOpts = array_merge($this->guzzleOpts, $options);
        return $this;
    }

    /** Return the response asynchronously (returns a GuzzleHttp Promise). */
    public function async(): static
    {
        $this->isAsync = true;
        return $this;
    }

    /** Automatically call throw() on the response if it failed. */
    public function throwOnFailure(): static
    {
        $this->throwOnFailure = true;
        return $this;
    }

    // -------------------------------------------------------------------------
    // HTTP verbs
    // -------------------------------------------------------------------------

    /** GET request. Query params merged into URL. */
    public function get(string $url, array $query = []): HttpResponse
    {
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        return $this->send('GET', $url);
    }

    /** POST request. */
    public function post(string $url, array $data = []): HttpResponse
    {
        return $this->send('POST', $url, $data);
    }

    /** PUT request. */
    public function put(string $url, array $data = []): HttpResponse
    {
        return $this->send('PUT', $url, $data);
    }

    /** PATCH request. */
    public function patch(string $url, array $data = []): HttpResponse
    {
        return $this->send('PATCH', $url, $data);
    }

    /** DELETE request. */
    public function delete(string $url, array $data = []): HttpResponse
    {
        return $this->send('DELETE', $url, $data);
    }

    /** HEAD request (returns response with no body). */
    public function head(string $url, array $query = []): HttpResponse
    {
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }
        return $this->send('HEAD', $url);
    }

    // -------------------------------------------------------------------------
    // Pool (concurrent requests)
    // -------------------------------------------------------------------------

    /**
     * Execute a pool of concurrent requests.
     *
     * Usage:
     *   $responses = Http::pool(function (HttpPool $pool) {
     *       return [
     *           $pool->get('https://api.example.com/users'),
     *           $pool->get('https://api.example.com/posts'),
     *       ];
     *   });
     *
     * @return list<HttpResponse>
     */
    public static function pool(callable $callback): array
    {
        $pool = new HttpPool();
        $requests = $callback($pool);
        if (empty($requests)) return [];

        $guzzle   = new Guzzle(['timeout' => 30]);
        $promises = [];

        foreach ($requests as $key => $request) {
            if ($request instanceof PendingGuzzleRequest) {
                $promises[$key] = $guzzle->requestAsync(
                    $request->method,
                    $request->url,
                    $request->options
                );
            }
        }

        $results   = PromiseUtils::settle($promises)->wait();
        $responses = [];

        foreach ($results as $key => $result) {
            if ($result['state'] === 'fulfilled') {
                $responses[$key] = HttpResponse::fromPsr7($result['value']);
            } else {
                // Rejected — return a synthetic 0 response
                $responses[$key] = new HttpResponse(0, [], $result['reason']?->getMessage() ?? 'Request failed');
            }
        }

        return $responses;
    }

    // -------------------------------------------------------------------------
    // Core sender
    // -------------------------------------------------------------------------

    private function send(string $method, string $url, array $data = []): HttpResponse
    {
        // --- Fake / recording ---
        Http::recordRequest($method, $url, $data);
        if (Http::isFaking()) {
            $descriptor = Http::matchFake($url);
            $response   = HttpResponse::fromFake($descriptor ?? Http::response([], 200));
            if ($this->throwOnFailure) $response->throw();
            return $response;
        }

        // --- Build Guzzle options ---
        $options = array_merge($this->guzzleOpts, [
            'timeout' => $this->timeout,
            'headers' => $this->buildHeaders(),
        ]);
        $options = array_merge($options, $this->buildBody($data));

        // --- Send with retry ---
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->retries) {
            try {
                $guzzle  = new Guzzle(['http_errors' => false]);

                if ($this->isAsync) {
                    // Return a promise wrapper — caller must ->wait()
                    $promise = $guzzle->requestAsync($method, $url, $options);
                    $psr7    = $promise->wait();
                    $response = HttpResponse::fromPsr7($psr7);
                } else {
                    $psr7     = $guzzle->request($method, $url, $options);
                    $response = HttpResponse::fromPsr7($psr7);
                }

                if ($this->throwOnFailure) $response->throw();
                return $response;

            } catch (\Throwable $e) {
                $lastException = $e;
                $attempt++;
                if ($attempt <= $this->retries && $this->retryDelay > 0) {
                    usleep($this->retryDelay * 1000);
                }
            }
        }

        throw $lastException ?? new \RuntimeException("HTTP {$method} {$url} failed.");
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildHeaders(): array
    {
        $headers = $this->headers;

        if ($this->bodyFormat === 'json' && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }
        if ($this->bodyFormat === 'form' && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }
        if ($this->bodyFormat === 'raw' && $this->rawBodyType && !isset($headers['Content-Type'])) {
            $headers['Content-Type'] = $this->rawBodyType;
        }

        return $headers;
    }

    private function buildBody(array $data): array
    {
        if ($this->bodyFormat === 'raw') {
            return ['body' => $this->rawBody ?? ''];
        }

        if ($this->bodyFormat === 'multipart') {
            $parts = $this->attachments;
            foreach ($data as $name => $value) {
                $parts[] = ['name' => $name, 'contents' => (string) $value];
            }
            return ['multipart' => $parts];
        }

        if (empty($data)) return [];

        if ($this->bodyFormat === 'form') {
            return ['form_params' => $data];
        }

        // Default: JSON
        return ['json' => $data];
    }
}

// =============================================================================
// HttpPool — builder for Http::pool()
// =============================================================================

/** @internal */
class PendingGuzzleRequest
{
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array  $options = [],
    ) {}
}

class HttpPool
{
    public function get(string $url, array $query = []): PendingGuzzleRequest
    {
        if ($query) $url .= '?' . http_build_query($query);
        return new PendingGuzzleRequest('GET', $url);
    }

    public function post(string $url, array $data = []): PendingGuzzleRequest
    {
        return new PendingGuzzleRequest('POST', $url, ['json' => $data]);
    }

    public function put(string $url, array $data = []): PendingGuzzleRequest
    {
        return new PendingGuzzleRequest('PUT', $url, ['json' => $data]);
    }

    public function delete(string $url, array $data = []): PendingGuzzleRequest
    {
        return new PendingGuzzleRequest('DELETE', $url, ['json' => $data]);
    }
}
