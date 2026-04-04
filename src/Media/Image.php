<?php
declare(strict_types=1);

namespace Nemesis\Media;

class Image {
    protected static $driver;

    public static function load($path) {
        return self::getDriver()->load($path);
    }

    public static function create($width, $height, $background = null) {
        return self::getDriver()->create($width, $height, $background);
    }

    protected static function getDriver() {
        if (!self::$driver) {
            $driverName = getenv('IMAGE_DRIVER') ?: (extension_loaded('imagick') ? 'imagick' : 'gd');
            
            if ($driverName === 'imagick' && extension_loaded('imagick')) {
                self::$driver = new \Nemesis\Media\Drivers\ImagickDriver();
            } else {
                self::$driver = new \Nemesis\Media\Drivers\GdDriver();
            }
        }
        return self::$driver;
    }

    // Added: 2026-04-03

    /**
     * Parse a CSS hex color (#rgb or #rrggbb) into an [r, g, b] integer array.
     * Returns null on invalid input.
     *
     *   Image::hexToRgb('#3af')     → [51, 170, 255]
     *   Image::hexToRgb('#ff5733')  → [255, 87, 51]
     */
    public static function hexToRgb(string $hex): ?array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return null;
        }
        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Return any image file as a base64 data URI, or null if the file doesn't exist.
     * MIME type is auto-detected via finfo; falls back to image/jpeg.
     *
     *   Image::toDataUri('/storage/avatars/photo.jpg')
     *   → 'data:image/jpeg;base64,/9j/4AAQ...'
     */
    public static function toDataUri(string $path): ?string
    {
        if (!file_exists($path)) return null;
        $data = file_get_contents($path);
        if ($data === false) return null;
        $mime = mime_content_type($path) ?: 'image/jpeg';
        return "data:{$mime};base64," . base64_encode($data);
    }
}
