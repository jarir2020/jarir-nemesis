<?php
declare(strict_types=1);

// Nemesis 5.0.0 | HTTP — Upload Result Value Object | Added: 2026-04-03

namespace Nemesis\Http;

/**
 * Typed result from FileValidator.
 *
 * On success: $result->success === true, $result->path has the relative path.
 * On failure: $result->success === false, $result->error has the message.
 *
 * Also implements __toString() for backward-compat with the original
 * 'ERROR:message' string pattern:
 *   (string) $result  →  '/uploads/img.jpg'  OR  'ERROR:Invalid MIME type...'
 */
readonly class UploadResult
{
    public function __construct(
        public bool    $success,
        public ?string $path  = null,
        public ?string $error = null,
    ) {}

    public static function ok(string $path): self
    {
        return new self(true, $path, null);
    }

    public static function fail(string $error): self
    {
        return new self(false, null, $error);
    }

    public function failed(): bool
    {
        return !$this->success;
    }

    /**
     * Backward-compatible string representation.
     * Returns the relative path on success, or 'ERROR:...' on failure.
     */
    public function __toString(): string
    {
        return $this->success
            ? (string) $this->path
            : 'ERROR:' . (string) $this->error;
    }
}
