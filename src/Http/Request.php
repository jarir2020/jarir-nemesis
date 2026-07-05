<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 3 — Immutable Request attributes | Updated: 2026-04-02

namespace Nemesis\Http;

class Request
{
    protected array $data;
    protected array $headers;

    /**
     * Arbitrary attributes attached via middleware (e.g. authenticated user).
     * Use withAttribute() to set; getAttribute() to read.
     */
    protected array $attributes = [];

    public function __construct()
    {
        $this->data    = array_merge($_GET, $_POST, $this->getJsonInput());
        $this->headers = $this->getAllHeaders();
    }

    // -------------------------------------------------------------------------
    // Immutable attribute bag — Phase 3
    // Middleware attaches data (e.g. 'auth.user') without mutating the request.
    // -------------------------------------------------------------------------

    /**
     * Return a clone of this request with the given attribute set.
     * Follows the immutable pattern — original is never changed.
     */
    public function withAttribute(string $key, mixed $value): static
    {
        $clone = clone $this;
        $clone->attributes[$key] = $value;
        return $clone;
    }

    /**
     * Read a middleware-attached attribute.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Return all attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    // -------------------------------------------------------------------------
    // Input
    // -------------------------------------------------------------------------

    public function all(): array
    {
        return $this->data;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    // -------------------------------------------------------------------------
    // File uploads — Added: 2026-04-03
    // -------------------------------------------------------------------------

    /**
     * Retrieve an uploaded file by input name.
     * Returns null when the input is absent or had an upload error.
     */
    public function file(string $name): ?\Nemesis\Http\UploadedFile
    {
        return \Nemesis\Http\UploadedFile::fromGlobal($name);
    }

    /**
     * True when the request contains a file with the given input name.
     */
    public function hasFile(string $name): bool
    {
        return isset($_FILES[$name])
            && ($_FILES[$name]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Retrieve all uploaded files for a multi-file input.
     *
     * @return \Nemesis\Http\UploadedFile[]
     */
    public function files(string $name): array
    {
        return \Nemesis\Http\UploadedFile::fromGlobalAll($name);
    }

    // -------------------------------------------------------------------------
    // Headers
    // -------------------------------------------------------------------------

    public function header(string $key, mixed $default = null): mixed
    {
        $upper = str_replace('-', '_', strtoupper($key));
        return $this->headers[$upper]
            ?? $this->headers[str_replace('_', '-', $upper)]
            ?? $default;
    }

    /** Extract Bearer token from Authorization header. Added: 2026-04-06 */
    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization', '');
        if (is_string($auth) && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Request meta
    // -------------------------------------------------------------------------

    /** @var array<string, mixed> */
    private array $meta = [];

    /** Store arbitrary metadata on the request (e.g. auth payload). Added: 2026-04-06 */
    public function setMeta(string $key, mixed $value): void
    {
        $this->meta[$key] = $value;
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    public function method(): string
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if ($method === 'POST') {
            $override = $this->input('_method', $this->header('X-HTTP-Method-Override', null));
            if (is_string($override) && $override !== '') {
                $override = strtoupper(trim($override));
                if (in_array($override, ['PUT', 'PATCH', 'DELETE', 'OPTIONS'], true)) {
                    return $override;
                }
            }
        }

        return $method;
    }

    public function uri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    /**
     * Validate the request data against the given rules.
     * Throws ValidationException on failure; returns validated data on success.
     *
     * @param  array<string, string|array>  $rules
     * @return array<string, mixed>
     * @throws \Nemesis\Core\ValidationException
     */
    public function validate(array $rules): array
    {
        $validator = new \Nemesis\Core\Validator();
        if (!$validator->validate($this->data, $rules)) {
            throw new \Nemesis\Core\ValidationException($validator->errors());
        }
        return $this->data;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    protected function getAllHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return array_change_key_case((array) getallheaders(), CASE_UPPER);
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headers[substr($name, 5)] = $value;
            }
        }
        return $headers;
    }

    protected function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode((string) $input, true) ?? [];
    }
}
