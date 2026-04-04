<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 9 — i18n Language Loader | Updated: 2026-04-03

namespace Nemesis\I18n;

/**
 * Language / i18n loader.
 *
 * Language files live in  app/Language/{locale}/{group}.php
 * and return an associative array of key → string.
 *
 * Usage:
 *   Language::setLocale('ar');
 *   echo __('app.welcome', ['app' => 'Nemesis']);   // مرحباً بك في Nemesis!
 *   echo trans('validation.required', ['field' => 'name']);
 *
 * Fallback: if the key is not found in the current locale,
 *           the 'en' locale is tried before returning the raw key.
 */
class Language
{
    private static string $locale       = 'en';
    private static string $fallback     = 'en';
    private static string $langPath     = '';
    private static array  $loaded       = [];  // ['en.app' => [...], ...]

    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    public static function setFallback(string $locale): void
    {
        self::$fallback = $locale;
    }

    public static function setPath(string $path): void
    {
        self::$langPath = rtrim($path, '/\\');
    }

    /** Flush loaded cache (useful in tests). */
    public static function flush(): void
    {
        self::$loaded = [];
    }

    // -------------------------------------------------------------------------
    // Translation
    // -------------------------------------------------------------------------

    /**
     * Translate a key.
     *
     * @param  string               $key     e.g. 'app.welcome'  or  'validation.required'
     * @param  array<string,mixed>  $replace Named placeholders: ['app' => 'Nemesis']
     * @param  string|null          $locale  Override locale for this call
     */
    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= self::$locale;

        [$group, $item] = self::parseKey($key);

        // Try requested locale first, then fallback
        $line = self::getFromLocale($locale, $group, $item)
             ?? self::getFromLocale(self::$fallback, $group, $item)
             ?? $key;

        return self::interpolate((string) $line, $replace);
    }

    /**
     * Check whether a translation key exists.
     */
    public static function has(string $key, ?string $locale = null): bool
    {
        $locale ??= self::$locale;
        [$group, $item] = self::parseKey($key);
        return self::getFromLocale($locale, $group, $item) !== null
            || self::getFromLocale(self::$fallback, $group, $item) !== null;
    }

    /**
     * Return all lines from a group file.
     * e.g.  Language::all('validation')
     */
    public static function all(string $group, ?string $locale = null): array
    {
        $locale ??= self::$locale;
        return self::load($locale, $group);
    }

    // -------------------------------------------------------------------------
    // Internal
    // -------------------------------------------------------------------------

    private static function parseKey(string $key): array
    {
        if (str_contains($key, '.')) {
            [$group, $item] = explode('.', $key, 2);
            return [$group, $item];
        }
        return [$key, null];
    }

    private static function getFromLocale(string $locale, string $group, ?string $item): mixed
    {
        $lines = self::load($locale, $group);
        if ($item === null) return $lines ?: null;
        return $lines[$item] ?? null;
    }

    private static function load(string $locale, string $group): array
    {
        $cacheKey = "{$locale}.{$group}";
        if (isset(self::$loaded[$cacheKey])) {
            return self::$loaded[$cacheKey];
        }

        $path = self::resolvePath($locale, $group);
        if ($path !== null && file_exists($path)) {
            $lines = require $path;
            self::$loaded[$cacheKey] = is_array($lines) ? $lines : [];
        } else {
            self::$loaded[$cacheKey] = [];
        }

        return self::$loaded[$cacheKey];
    }

    private static function resolvePath(string $locale, string $group): ?string
    {
        // 1. Use explicitly set path
        if (self::$langPath !== '') {
            return self::$langPath . '/' . $locale . '/' . $group . '.php';
        }

        // 2. Auto-detect from this file's location
        $candidate = __DIR__ . '/../../app/Language/' . $locale . '/' . $group . '.php';
        return $candidate;
    }

    private static function interpolate(string $line, array $replace): string
    {
        if (empty($replace)) return $line;

        foreach ($replace as $key => $value) {
            $line = str_replace(
                [':' . $key, ':' . strtoupper($key), ':' . ucfirst($key)],
                [(string) $value, strtoupper((string) $value), ucfirst((string) $value)],
                $line
            );
        }

        return $line;
    }
}
