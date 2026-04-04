<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 8 — Testing Infrastructure | Created: 2026-04-02

namespace Nemesis\Testing;

/**
 * HttpTestClient
 *
 * Makes HTTP requests against the running app (via cURL) or can be subclassed
 * for in-process testing. Returns TestResponse for fluent assertions.
 *
 * Usage in a test:
 *   $client = new HttpTestClient('http://localhost');
 *   $client->get('/users')->assertOk()->assertJson(['count' => 3]);
 */
class HttpTestClient
{
    protected array  $defaultHeaders = [];
    protected ?string $token         = null;

    public function __construct(
        protected string $baseUrl = 'http://localhost'
    ) {}

    // -------------------------------------------------------------------------
    // Auth helpers
    // -------------------------------------------------------------------------

    public function withToken(string $token, string $type = 'Bearer'): static
    {
        $clone = clone $this;
        $clone->defaultHeaders['Authorization'] = "{$type} {$token}";
        return $clone;
    }

    public function withHeaders(array $headers): static
    {
        $clone = clone $this;
        $clone->defaultHeaders = array_merge($clone->defaultHeaders, $headers);
        return $clone;
    }

    // -------------------------------------------------------------------------
    // HTTP verb shortcuts
    // -------------------------------------------------------------------------

    public function get(string $uri, array $headers = []): TestResponse
    {
        return $this->call('GET', $uri, [], $headers);
    }

    public function post(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, $data, $headers);
    }

    public function put(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('PUT', $uri, $data, $headers);
    }

    public function patch(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('PATCH', $uri, $data, $headers);
    }

    public function delete(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('DELETE', $uri, $data, $headers);
    }

    public function postJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->call('POST', $uri, $data, array_merge(
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            $headers
        ), json: true);
    }

    // -------------------------------------------------------------------------
    // Core request
    // -------------------------------------------------------------------------

    public function call(
        string $method,
        string $uri,
        array  $data    = [],
        array  $headers = [],
        bool   $json    = false,
    ): TestResponse {
        $url     = rtrim($this->baseUrl, '/') . '/' . ltrim($uri, '/');
        $headers = array_merge($this->defaultHeaders, $headers);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  strtoupper($method));
        curl_setopt($ch, CURLOPT_HEADER,         true);
        curl_setopt($ch, CURLOPT_TIMEOUT,        10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        if (!empty($data)) {
            if ($method === 'GET') {
                $url .= '?' . http_build_query($data);
            } elseif ($json) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }

        curl_setopt($ch, CURLOPT_URL, $url);

        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = "{$name}: {$value}";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

        $raw        = (string) curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr($raw, 0, $headerSize);
        $body       = substr($raw, $headerSize);

        return new TestResponse($statusCode, $body, $rawHeaders);
    }
}
