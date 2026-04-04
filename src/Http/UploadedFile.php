<?php
declare(strict_types=1);

// Nemesis 5.0.0 | HTTP — Uploaded File Wrapper | Added: 2026-04-03

namespace Nemesis\Http;

/**
 * Wraps a single entry from the global $_FILES superglobal.
 *
 * Provides the same interface as the validator's expectations:
 *   getClientOriginalName(), getClientOriginalExtension(), getMimeType(),
 *   getPathname(), getSize(), isValid(), move()
 *
 * getMimeType() always uses server-side finfo detection — never trusts the
 * browser-supplied Content-Type, which can be trivially spoofed.
 *
 * Usage:
 *   $file = UploadedFile::fromGlobal('avatar');
 *   if ($file && $file->isValid()) { ... }
 */
class UploadedFile
{
    public function __construct(private readonly array $data) {}

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Build from $_FILES[$inputName].
     * Returns null when the input doesn't exist or is not a real upload.
     */
    public static function fromGlobal(string $inputName): ?self
    {
        if (!isset($_FILES[$inputName]) || !is_array($_FILES[$inputName])) {
            return null;
        }

        $f = $_FILES[$inputName];

        // Normalise single-file entry
        if (!is_array($f['name'])) {
            return new self($f);
        }

        // Multiple files — return the first one only
        return new self([
            'name'     => $f['name'][0],
            'type'     => $f['type'][0],
            'tmp_name' => $f['tmp_name'][0],
            'error'    => $f['error'][0],
            'size'     => $f['size'][0],
        ]);
    }

    /**
     * Return ALL uploaded files for a multi-file input.
     *
     * @return self[]
     */
    public static function fromGlobalAll(string $inputName): array
    {
        if (!isset($_FILES[$inputName]) || !is_array($_FILES[$inputName])) {
            return [];
        }

        $f = $_FILES[$inputName];

        if (!is_array($f['name'])) {
            return [new self($f)];
        }

        $files = [];
        foreach (array_keys($f['name']) as $i) {
            $files[] = new self([
                'name'     => $f['name'][$i],
                'type'     => $f['type'][$i],
                'tmp_name' => $f['tmp_name'][$i],
                'error'    => $f['error'][$i],
                'size'     => $f['size'][$i],
            ]);
        }
        return $files;
    }

    // -------------------------------------------------------------------------
    // File info
    // -------------------------------------------------------------------------

    /** Original filename as submitted by the browser. */
    public function getClientOriginalName(): string
    {
        return (string) ($this->data['name'] ?? '');
    }

    /** Extension extracted from the original filename (lowercased). */
    public function getClientOriginalExtension(): string
    {
        return strtolower(pathinfo($this->getClientOriginalName(), PATHINFO_EXTENSION));
    }

    /**
     * Server-side MIME type via finfo (NOT the browser-supplied type).
     * Falls back to mime_content_type(), then 'application/octet-stream'.
     */
    public function getMimeType(): string
    {
        $tmp = $this->getPathname();
        if ($tmp === '' || !file_exists($tmp)) {
            return 'application/octet-stream';
        }

        if (function_exists('finfo_file')) {
            $fi   = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fi, $tmp);
            finfo_close($fi);
            if ($mime !== false) return $mime;
        }

        return mime_content_type($tmp) ?: 'application/octet-stream';
    }

    /** Temporary file path on the server. */
    public function getPathname(): string
    {
        return (string) ($this->data['tmp_name'] ?? '');
    }

    /** File size in bytes. */
    public function getSize(): int
    {
        return (int) ($this->data['size'] ?? 0);
    }

    /** PHP upload error code (0 = UPLOAD_ERR_OK). */
    public function getError(): int
    {
        return (int) ($this->data['error'] ?? UPLOAD_ERR_NO_FILE);
    }

    /** Human-readable upload error message. */
    public function getErrorMessage(): string
    {
        return match ($this->getError()) {
            UPLOAD_ERR_OK         => 'No error',
            UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload',
            default               => 'Unknown upload error',
        };
    }

    /** True when the file was actually uploaded with no PHP errors. */
    public function isValid(): bool
    {
        return $this->getError() === UPLOAD_ERR_OK
            && is_uploaded_file($this->getPathname());
    }

    // -------------------------------------------------------------------------
    // File operations
    // -------------------------------------------------------------------------

    /**
     * Move the uploaded file to its permanent location.
     * Uses move_uploaded_file() which verifies the file came from an upload.
     *
     * @param  string $directory  Destination directory (must exist).
     * @param  string $filename   New filename inside the directory.
     * @return bool
     */
    public function move(string $directory, string $filename): bool
    {
        $dest = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $filename;
        return move_uploaded_file($this->getPathname(), $dest);
    }
}
