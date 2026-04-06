<?php
declare(strict_types=1);

// Nemesis 6.0 | Phase 19 — HTTP Client: HttpResponse | 2026-04-06

namespace Nemesis\Http\Client;

use Psr\Http\Message\ResponseInterface;

/**
 * HttpResponse — wraps a PSR-7 response (or a fake descriptor).
 *
 * Examples:
 *   $r = Http::get('https://api.example.com/users');
 *   $r->json();           // ['data' => [...]]
 *   $r->json('data.0.id'); // dot-notation access
 *   $r->status();         // 200
 *   $r->ok();             // true
 *   $r->throw();          // throws HttpResponseException if failed
 */
class HttpResponse
{
    private string $bodyString  = '';
    private ?array $decodedJson = null;

    // -------------------------------------------------------------------------
    // Construction
    // -------------------------------------------------------------------------

    public function __construct(
        private readonly int    $statusCode,
        private readonly array  $headers,
        string                  $body,
        private readonly bool   $wasFaked = false,
    ) {
        $this->bodyString = $body;
    }

    /** Build from a PSR-7 ResponseInterface (real Guzzle response). */
    public static function fromPsr7(ResponseInterface $response): self
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower($name)] = implode(', ', $values);
        }

        return new self(
            statusCode: $response->getStatusCode(),
            headers:    $headers,
            body:       (string) $response->getBody(),
        );
    }

    /** Build from a fake descriptor array created by Http::response(). */
    public static function fromFake(array $descriptor): self
    {
        $body   = $descriptor['body'] ?? null;
        $status = (int) ($descriptor['status'] ?? 200);
        $hdrs   = $descriptor['headers'] ?? [];

        // Normalise body to string
        $bodyStr = is_array($body)
            ? (string) json_encode($body)
            : (string) ($body ?? '');

        return new self(
            statusCode: $status,
            headers:    $hdrs,
            body:       $bodyStr,
            wasFaked:   true,
        );
    }

    // -------------------------------------------------------------------------
    // Body
    // -------------------------------------------------------------------------

    /** Raw response body string. */
    public function body(): string
    {
        return $this->bodyString;
    }

    /**
     * Decoded JSON body.
     *
     * @param string|null $key Optional dot-notation key: 'data.0.id'
     */
    public function json(string $key = null): mixed
    {
        if ($this->decodedJson === null) {
            $this->decodedJson = (array) json_decode($this->bodyString, true);
        }

        if ($key === null) {
            return $this->decodedJson;
        }

        return $this->dotGet($this->decodedJson, $key);
    }

    /** Decoded JSON cast to object. */
    public function object(): ?object
    {
        $decoded = json_decode($this->bodyString);
        return is_object($decoded) ? $decoded : null;
    }

    // -------------------------------------------------------------------------
    // Status
    // -------------------------------------------------------------------------

    public function status(): int    { return $this->statusCode; }
    public function ok(): bool       { return $this->statusCode === 200; }
    public function created(): bool  { return $this->statusCode === 201; }
    public function accepted(): bool { return $this->statusCode === 202; }
    public function noContent(): bool { return $this->statusCode === 204; }

    public function successful(): bool  { return $this->statusCode >= 200 && $this->statusCode < 300; }
    public function redirect(): bool    { return $this->statusCode >= 300 && $this->statusCode < 400; }
    public function clientError(): bool { return $this->statusCode >= 400 && $this->statusCode < 500; }
    public function serverError(): bool { return $this->statusCode >= 500; }
    public function failed(): bool      { return !$this->successful(); }
    public function unauthorized(): bool { return $this->statusCode === 401; }
    public function forbidden(): bool    { return $this->statusCode === 403; }
    public function notFound(): bool     { return $this->statusCode === 404; }
    public function unprocessable(): bool { return $this->statusCode === 422; }
    public function tooManyRequests(): bool { return $this->statusCode === 429; }

    // -------------------------------------------------------------------------
    // Headers
    // -------------------------------------------------------------------------

    public function header(string $header): string
    {
        return $this->headers[strtolower($header)] ?? '';
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $header): bool
    {
        return isset($this->headers[strtolower($header)]);
    }

    // -------------------------------------------------------------------------
    // Throwing
    // -------------------------------------------------------------------------

    /**
     * Throw an exception if the response indicates a client or server error.
     *
     * @throws HttpResponseException
     */
    public function throw(): static
    {
        if ($this->failed()) {
            throw new HttpResponseException($this);
        }
        return $this;
    }

    /**
     * Throw if the given callable returns true.
     * @throws HttpResponseException
     */
    public function throwIf(callable $condition): static
    {
        if ($condition($this)) {
            throw new HttpResponseException($this);
        }
        return $this;
    }

    // -------------------------------------------------------------------------
    // Misc
    // -------------------------------------------------------------------------

    public function wasFaked(): bool { return $this->wasFaked; }

    public function toArray(): array
    {
        return $this->json() ?? [];
    }

    // -------------------------------------------------------------------------
    // Dot-notation getter
    // -------------------------------------------------------------------------

    private function dotGet(array $data, string $key): mixed
    {
        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }
            $data = $data[$segment];
        }
        return $data;
    }
}

// =============================================================================
// HttpResponseException
// =============================================================================

class HttpResponseException extends \RuntimeException
{
    public function __construct(private readonly HttpResponse $response)
    {
        parent::__construct(
            "HTTP request failed with status {$response->status()}.",
            $response->status()
        );
    }

    public function response(): HttpResponse { return $this->response; }
}
