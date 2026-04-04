<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Created: 2026-04-02
namespace Nemesis\Exceptions\Concerns;

/**
 * Exceptions implementing this interface are reported to external services
 * (loggers, Sentry, etc.) by the ErrorHandler.
 * Use dontReport() pattern to suppress per exception type.
 */
interface ReportableException
{
    public function report(): void;
}
