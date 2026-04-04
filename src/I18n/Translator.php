<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 10 fine-tune — Machine Translator | Added: 2026-04-03

namespace Nemesis\I18n;

/**
 * Generic machine-translation helper.
 *
 * Uses the unofficial Google Translate endpoint (no API key required).
 * Falls back gracefully when the network is unavailable.
 *
 * Supports Guzzle (when available as a transitive dep) with a native-curl
 * fallback — keeping the "zero hard dependency" promise.
 *
 * Usage:
 *   Translator::translate('Hello', 'bn');             // → 'হ্যালো'
 *   Translator::translate('مرحبا', 'en', 'ar');      // → 'Hello'
 *   Translator::translate('Hola', 'fr', 'auto');     // auto-detect source
 *
 *   // Convenience shortcuts
 *   Translator::enToBn('Good morning');              // → 'সুপ্রভাত'
 *   Translator::toBn('Good morning', 'en');
 *   Translator::toEn('সুপ্রভাত', 'bn');
 *
 *   // Batch
 *   Translator::translateMany(['Yes', 'No'], 'bn'); // → ['হ্যাঁ', 'না']
 *
 *   // Global helper
 *   translate('Good morning', 'fr');               // → 'Bonjour'
 */
class Translator
{
    // -------------------------------------------------------------------------
    // Config
    // -------------------------------------------------------------------------

    /** Seconds before each HTTP request times out. */
    private const TIMEOUT = 3;

    /** Base URL for the unofficial Google Translate API. */
    private const ENDPOINT = 'https://translate.googleapis.com/translate_a/single';

    /**
     * Optional persistent cache directory (null = memory-only).
     * Set via Translator::setCacheDir('storage/framework/translations')
     */
    private static ?string $cacheDir = null;

    /** In-memory cache: md5(source|target|text) → translated string */
    private static array $memory = [];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Translate a string from $source language to $target language.
     *
     * @param  string $text    The text to translate.
     * @param  string $target  BCP-47 language code, e.g. 'bn', 'fr', 'ar', 'zh-CN'
     * @param  string $source  Source language code, or 'auto' for auto-detection
     * @return string          Translated text, or the original on failure.
     */
    public static function translate(string $text, string $target, string $source = 'auto'): string
    {
        if ($text === '') return '';
        if (strtolower($source) === strtolower($target)) return $text;

        $cacheKey = md5("{$source}|{$target}|{$text}");

        // 1. In-memory cache
        if (isset(self::$memory[$cacheKey])) {
            return self::$memory[$cacheKey];
        }

        // 2. File cache
        if (self::$cacheDir !== null) {
            $hit = self::readFileCache($cacheKey);
            if ($hit !== null) {
                self::$memory[$cacheKey] = $hit;
                return $hit;
            }
        }

        // 3. HTTP translation
        $translated = self::request($text, $target, $source) ?? $text;

        // 4. Optional post-processing (locale-specific digit/script mapping)
        $translated = self::postProcess($translated, $target);

        // 5. Store in caches
        self::$memory[$cacheKey] = $translated;
        if (self::$cacheDir !== null) {
            self::writeFileCache($cacheKey, $translated);
        }

        return $translated;
    }

    /**
     * Translate multiple strings in a single call.
     * Each string is translated independently (separate API requests).
     *
     * @param  string[] $texts
     * @return string[]
     */
    public static function translateMany(array $texts, string $target, string $source = 'auto'): array
    {
        return array_map(
            fn (string $text) => self::translate($text, $target, $source),
            $texts
        );
    }

    /**
     * Detect the language of the given text.
     * Returns the BCP-47 code (e.g. 'en', 'bn', 'ar') or 'und' on failure.
     */
    public static function detect(string $text): string
    {
        if ($text === '') return 'und';

        $url  = self::buildUrl($text, 'en', 'auto');
        $body = self::fetch($url);

        if ($body === null) return 'und';

        $data = json_decode($body, true);

        // The detected language lives at $body[2] in the unofficial API response
        return (string) ($data[2] ?? 'und');
    }

    // -------------------------------------------------------------------------
    // Convenience shortcuts
    // -------------------------------------------------------------------------

    /**
     * English → Bengali (Bangla).
     * Preserves Bengali digit form after translation (optional pass).
     */
    public static function enToBn(string $text): string
    {
        return self::translate($text, 'bn', 'en');
    }

    /**
     * Any language → Bengali.
     */
    public static function toBn(string $text, string $source = 'auto'): string
    {
        return self::translate($text, 'bn', $source);
    }

    /**
     * Any language → English.
     */
    public static function toEn(string $text, string $source = 'auto'): string
    {
        return self::translate($text, 'en', $source);
    }

    // -------------------------------------------------------------------------
    // Cache management
    // -------------------------------------------------------------------------

    /**
     * Set a directory for persistent file-based translation cache.
     * Pass null to disable file caching.
     */
    public static function setCacheDir(?string $dir): void
    {
        self::$cacheDir = $dir !== null ? rtrim($dir, '/\\') : null;
    }

    /**
     * Clear in-memory (and optionally file) cache.
     */
    public static function flush(bool $files = false): void
    {
        self::$memory = [];

        if ($files && self::$cacheDir !== null && is_dir(self::$cacheDir)) {
            foreach (glob(self::$cacheDir . '/*.txt') ?: [] as $f) {
                @unlink($f);
            }
        }
    }

    // -------------------------------------------------------------------------
    // HTTP layer — Guzzle preferred, curl fallback, file_get_contents last resort
    // -------------------------------------------------------------------------

    private static function request(string $text, string $target, string $source): ?string
    {
        $url  = self::buildUrl($text, $target, $source);
        $body = self::fetch($url);

        if ($body === null) return null;

        $data = json_decode($body, true);

        // Google unofficial API: [[["translated","original",null,null,10],...],...]
        if (!isset($data[0]) || !is_array($data[0])) return null;

        // Concatenate all translated segments (long texts are split)
        $result = '';
        foreach ($data[0] as $segment) {
            if (isset($segment[0]) && is_string($segment[0])) {
                $result .= $segment[0];
            }
        }

        return $result !== '' ? $result : null;
    }

    private static function buildUrl(string $text, string $target, string $source): string
    {
        return self::ENDPOINT . '?' . http_build_query([
            'client' => 'gtx',
            'sl'     => $source,
            'tl'     => $target,
            'dt'     => 't',
            'q'      => $text,
        ]);
    }

    /**
     * Perform the GET request via Guzzle → curl → file_get_contents.
     * Returns raw response body string or null on any error.
     */
    private static function fetch(string $url): ?string
    {
        // --- Strategy 1: Guzzle (if available as transitive dep) ---
        if (class_exists('GuzzleHttp\Client')) {
            try {
                $client   = new \GuzzleHttp\Client(['timeout' => self::TIMEOUT]);
                $response = $client->get($url, [
                    'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; Nemesis/5.0)'],
                ]);
                return (string) $response->getBody();
            } catch (\Throwable) {
                // Fall through to curl
            }
        }

        // --- Strategy 2: native curl ---
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; Nemesis/5.0)',
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            $err  = curl_errno($ch);
            curl_close($ch);

            if ($err === CURLE_OK && is_string($body)) {
                return $body;
            }
        }

        // --- Strategy 3: file_get_contents (requires allow_url_fopen) ---
        if (ini_get('allow_url_fopen')) {
            $ctx  = stream_context_create([
                'http' => [
                    'timeout'        => self::TIMEOUT,
                    'user_agent'     => 'Mozilla/5.0 (compatible; Nemesis/5.0)',
                    'ignore_errors'  => true,
                ],
            ]);
            $body = @file_get_contents($url, false, $ctx);
            return is_string($body) ? $body : null;
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Post-processing (locale-specific script / digit mapping)
    // -------------------------------------------------------------------------

    /**
     * Apply locale-specific transformations after translation.
     * Currently handles Bengali (bn) digit conversion as an example.
     * Extend this map for other scripts that use non-ASCII numerals.
     */
    private static function postProcess(string $text, string $locale): string
    {
        return match (strtolower($locale)) {
            'bn'    => self::toLocalDigits($text, self::BENGALI_DIGITS),
            'ar'    => self::toLocalDigits($text, self::ARABIC_DIGITS),
            'fa',
            'ur'    => self::toLocalDigits($text, self::PERSIAN_DIGITS),
            default => $text,
        };
    }

    /**
     * Replace ASCII digits 0-9 with locale-specific equivalents.
     *
     * @param  array<int, string> $digitMap  10-element array: index = ASCII digit
     */
    private static function toLocalDigits(string $text, array $digitMap): string
    {
        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            $digitMap,
            $text
        );
    }

    // -------------------------------------------------------------------------
    // Digit maps
    // -------------------------------------------------------------------------

    /** Bengali / Bangla digits (০ ১ ২ ৩ ৪ ৫ ৬ ৭ ৮ ৯) */
    private const BENGALI_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

    /** Arabic-Indic digits (٠ ١ ٢ ٣ ٤ ٥ ٦ ٧ ٨ ٩) */
    private const ARABIC_DIGITS  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    /** Eastern Arabic / Persian digits (۰ ۱ ۲ ۳ ۴ ۵ ۶ ۷ ۸ ۹) */
    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    // -------------------------------------------------------------------------
    // File cache helpers
    // -------------------------------------------------------------------------

    private static function readFileCache(string $key): ?string
    {
        $file = self::$cacheDir . '/' . $key . '.txt';
        if (!file_exists($file)) return null;

        $content = @file_get_contents($file);
        return is_string($content) ? $content : null;
    }

    private static function writeFileCache(string $key, string $value): void
    {
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0755, true);
        }
        @file_put_contents(self::$cacheDir . '/' . $key . '.txt', $value);
    }
}
