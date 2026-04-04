<?php
declare(strict_types=1);

// Nemesis 5.0.0 | File Upload Utilities | Added: 2026-04-03

namespace Nemesis\Support;

/**
 * Upload helper — unique filenames, allowed extension/MIME lists.
 *
 * Usage:
 *   $name = Upload::uniqueFilename($employeeId, $uploadedFile);
 *   // → "1712100000_42_a3f9x7bq2k.jpg"
 *
 *   if (!Upload::allowedExtension($file->getClientOriginalExtension())) abort(422);
 *   if (!Upload::allowedMime($file->getMimeType())) abort(422);
 */
class Upload
{
    // -------------------------------------------------------------------------
    // Filename generation
    // -------------------------------------------------------------------------

    /**
     * Generate a unique filename: {timestamp}_{id}_{randomString}.{ext}
     *
     * The random segment is 16–24 chars long (cryptographically secure).
     * Compatible with any uploaded file object that exposes
     * getClientOriginalExtension(), or a plain extension string.
     *
     * @param  int|string              $id    Any identifier (user ID, employee ID, etc.)
     * @param  object|string           $file  An uploaded file object or a plain extension string.
     * @return string                         e.g. "1712100000_42_a3f9x7bq2k.jpg"
     */
    public static function uniqueFilename(int|string $id, object|string $file): string
    {
        $ext = is_string($file)
            ? ltrim(strtolower($file), '.')
            : strtolower(
                method_exists($file, 'getClientOriginalExtension')
                    ? $file->getClientOriginalExtension()
                    : (method_exists($file, 'extension') ? $file->extension() : '')
              );

        $random = Str::random(self::randomLength());
        return time() . '_' . $id . '_' . $random . ($ext !== '' ? '.' . $ext : '');
    }

    /**
     * Random length in the 16–24 range (mirrors the original randomNumber() intent).
     */
    private static function randomLength(): int
    {
        return random_int(16, 24);
    }

    // -------------------------------------------------------------------------
    // Extension / MIME validation
    // -------------------------------------------------------------------------

    /**
     * Allowed image file extensions (lowercase, no dot).
     *
     * @return string[]
     */
    public static function allowedExtensions(): array
    {
        return [
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp',
            'svg', 'heic', 'tiff', 'avif', 'eps', 'ai',
        ];
    }

    /**
     * Allowed image MIME types.
     *
     * @return string[]
     */
    public static function allowedMimes(): array
    {
        return [
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
            'image/bmp', 'image/webp', 'image/svg+xml', 'image/heic',
            'image/tiff', 'image/avif', 'application/postscript',
        ];
    }

    /**
     * Check whether an extension is in the allowed list.
     *
     * @param  string $ext  With or without leading dot, case-insensitive.
     */
    public static function allowedExtension(string $ext): bool
    {
        return in_array(strtolower(ltrim($ext, '.')), self::allowedExtensions(), true);
    }

    /**
     * Check whether a MIME type is in the allowed list.
     */
    public static function allowedMime(string $mime): bool
    {
        return in_array(strtolower($mime), self::allowedMimes(), true);
    }

    /**
     * Extract and lowercase a file extension from a path or filename.
     * e.g. 'photo.JPG' → 'jpg'
     */
    public static function extractExtension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    // -------------------------------------------------------------------------
    // Path helpers
    // -------------------------------------------------------------------------

    /**
     * Convert an absolute path to a web-relative path by stripping the
     * public directory prefix and normalising backslashes.
     *
     * findRelativePath('/var/www/html/public/uploads/photo.jpg')
     * → 'uploads/photo.jpg'
     */
    public static function findRelativePath(string $absolutePath): string
    {
        $public = function_exists('base_path')
            ? base_path('public')
            : (realpath(__DIR__ . '/../../public') ?: '');

        $rel = str_replace([$public, '\\'], ['', '/'], $absolutePath);
        return ltrim($rel, '/');
    }
}
