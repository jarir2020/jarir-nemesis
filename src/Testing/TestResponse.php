<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 8 — Testing Infrastructure | Created: 2026-04-02

namespace Nemesis\Testing;

/**
 * Wraps an HTTP response for fluent test assertions.
 * Returned by HttpTestClient and MakesHttpRequests helpers.
 */
class TestResponse
{
    protected ?array $decodedJson = null;

    public function __construct(
        protected int    $status,
        protected string $content,
        protected string $rawHeaders = '',
    ) {}

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getStatus(): int   { return $this->status; }
    public function getContent(): string { return $this->content; }

    /** Decode JSON body; returns null if body is not valid JSON. */
    public function json(?string $key = null): mixed
    {
        if ($this->decodedJson === null) {
            $this->decodedJson = json_decode($this->content, true) ?? [];
        }
        if ($key === null) return $this->decodedJson;
        return $this->decodedJson[$key] ?? null;
    }

    /** Parse raw header block into key→value array. */
    public function headers(): array
    {
        $headers = [];
        foreach (explode("\r\n", $this->rawHeaders) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }
        return $headers;
    }

    // -------------------------------------------------------------------------
    // Assertions — all return $this for chaining
    // -------------------------------------------------------------------------

    public function assertStatus(int $expected): static
    {
        if ($this->status !== $expected) {
            throw new \Exception(
                "Expected HTTP status {$expected}, got {$this->status}.\nBody: " . substr($this->content, 0, 200)
            );
        }
        return $this;
    }

    public function assertOk(): static       { return $this->assertStatus(200); }
    public function assertCreated(): static  { return $this->assertStatus(201); }
    public function assertNoContent(): static{ return $this->assertStatus(204); }
    public function assertNotFound(): static { return $this->assertStatus(404); }
    public function assertForbidden(): static{ return $this->assertStatus(403); }
    public function assertUnauthorized(): static { return $this->assertStatus(401); }

    public function assertRedirect(string $url = ''): static
    {
        if ($this->status < 300 || $this->status >= 400) {
            throw new \Exception("Expected a redirect (3xx), got {$this->status}.");
        }
        if ($url !== '') {
            $location = $this->headers()['location'] ?? '';
            if ($location !== $url) {
                throw new \Exception("Expected redirect to [{$url}], got [{$location}].");
            }
        }
        return $this;
    }

    /**
     * Assert JSON body contains all key/value pairs in $subset (shallow check).
     *
     * @param array<string, mixed> $subset
     */
    public function assertJson(array $subset): static
    {
        $body = $this->json();
        if ($body === null) {
            throw new \Exception("Response body is not valid JSON.");
        }
        foreach ($subset as $key => $value) {
            if (!array_key_exists($key, $body) || $body[$key] != $value) {
                throw new \Exception(
                    "JSON key [{$key}] does not match. Expected: " . json_encode($value) .
                    ", got: " . json_encode($body[$key] ?? null)
                );
            }
        }
        return $this;
    }

    /**
     * Assert a value at a dot-notation JSON path.
     * e.g. assertJsonPath('data.user.name', 'Alice')
     */
    public function assertJsonPath(string $path, mixed $expected): static
    {
        $value = $this->json();
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                throw new \Exception("JSON path [{$path}] does not exist.");
            }
            $value = $value[$segment];
        }
        if ($value != $expected) {
            throw new \Exception(
                "JSON path [{$path}] expected " . json_encode($expected) . ", got " . json_encode($value)
            );
        }
        return $this;
    }

    public function assertSee(string $text): static
    {
        if (!str_contains($this->content, $text)) {
            throw new \Exception("Response does not contain [{$text}].");
        }
        return $this;
    }

    public function assertNotSee(string $text): static
    {
        if (str_contains($this->content, $text)) {
            throw new \Exception("Response unexpectedly contains [{$text}].");
        }
        return $this;
    }

    public function assertHeader(string $name, string $expected): static
    {
        $value = $this->headers()[strtolower($name)] ?? null;
        if ($value !== $expected) {
            throw new \Exception(
                "Header [{$name}] expected [{$expected}], got [" . ($value ?? 'null') . "]."
            );
        }
        return $this;
    }

    public function assertJsonCount(int $count, string $key = ''): static
    {
        $data = $key ? $this->json($key) : $this->json();
        if (!is_array($data)) {
            throw new \Exception("JSON value at [{$key}] is not an array.");
        }
        if (count($data) !== $count) {
            throw new \Exception("Expected JSON array count {$count}, got " . count($data) . ".");
        }
        return $this;
    }
}
