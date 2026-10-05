<?php
declare(strict_types=1);

namespace Nemesis\Exceptions;

/**
 * Raised when an authorized FormRequest policy rejects the current request.
 */
class AuthorizationException extends ForbiddenException {}
