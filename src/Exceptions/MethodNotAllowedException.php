<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Created: 2026-04-02
namespace Nemesis\Exceptions;

class MethodNotAllowedException extends HttpException
{
    public function __construct(string $message = 'Method Not Allowed', \Throwable $previous = null)
    {
        parent::__construct(405, $message, $previous);
    }
}
