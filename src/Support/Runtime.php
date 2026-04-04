<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Runtime Environment Utilities | Added: 2026-04-03

namespace Nemesis\Support;

/**
 * Runtime helper — PHP ini tweaks for long-running or memory-heavy operations.
 *
 * Usage:
 *   Runtime::setMemoryAndTimeLimit();            // defaults: 1024M / 300s
 *   Runtime::setMemoryAndTimeLimit('512M', 120); // custom
 *   Runtime::setMemoryLimit('2048M');
 *   Runtime::setTimeLimit(600);
 *
 *   // For queue workers / imports / exports call at top of the job:
 *   Runtime::forHeavyJob();   // 1024M / 300s
 *   Runtime::forImport();     // 2048M / 600s
 */
class Runtime
{
    // -------------------------------------------------------------------------
    // Memory + time combined
    // -------------------------------------------------------------------------

    /**
     * Set both memory limit and max execution time.
     *
     * @param  string $memory         e.g. '1024M', '512M', '2G'
     * @param  int    $seconds        Max execution time in seconds
     */
    public static function setMemoryAndTimeLimit(
        string $memory  = '1024M',
        int    $seconds = 300,
    ): void {
        self::setMemoryLimit($memory);
        self::setTimeLimit($seconds);
    }

    /** Preset for heavy jobs (queue workers, large imports). */
    public static function forHeavyJob(): void
    {
        self::setMemoryAndTimeLimit('1024M', 300);
    }

    /** Preset for large import/export operations. */
    public static function forImport(): void
    {
        self::setMemoryAndTimeLimit('2048M', 600);
    }

    /** Preset for image processing batches. */
    public static function forImageProcessing(): void
    {
        self::setMemoryAndTimeLimit('512M', 120);
    }

    // -------------------------------------------------------------------------
    // Individual setters
    // -------------------------------------------------------------------------

    public static function setMemoryLimit(string $limit): void
    {
        ini_set('memory_limit', $limit);
    }

    /**
     * Set max_execution_time via both ini_set and set_time_limit.
     * set_time_limit resets the counter; ini_set caps it globally.
     */
    public static function setTimeLimit(int $seconds): void
    {
        ini_set('max_execution_time', (string) $seconds);
        set_time_limit($seconds);
    }

    // -------------------------------------------------------------------------
    // Introspection
    // -------------------------------------------------------------------------

    /**
     * Current memory limit as configured in PHP (may differ from system RAM).
     */
    public static function memoryLimit(): string
    {
        return ini_get('memory_limit') ?: '-1';
    }

    /**
     * Current peak memory usage in megabytes.
     */
    public static function peakMemoryMb(): float
    {
        return round(memory_get_peak_usage(true) / 1048576, 2);
    }

    /**
     * Current memory usage in megabytes.
     */
    public static function memoryUsageMb(): float
    {
        return round(memory_get_usage(true) / 1048576, 2);
    }
}
