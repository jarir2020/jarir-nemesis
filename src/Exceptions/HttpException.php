<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Created: 2026-04-02
namespace Nemesis\Exceptions;

/**
 * Represents an HTTP error response.
 * Carries a status code that the ErrorHandler uses to render the correct view.
 */
class HttpException extends NemesisException
{
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        \Throwable $previous = null
    ) {
        parent::__construct($message ?: $this->defaultMessage($statusCode), $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    private function defaultMessage(int $code): string
    {
        return match ($code) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            default => 'HTTP Error',
        };
    }
}
