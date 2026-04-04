<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Created: 2026-04-02
namespace Nemesis\Exceptions;

class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'Forbidden', \Throwable $previous = null)
    {
        parent::__construct(403, $message, $previous);
    }
}
