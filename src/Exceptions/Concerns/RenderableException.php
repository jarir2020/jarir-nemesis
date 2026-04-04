<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Created: 2026-04-02
namespace Nemesis\Exceptions\Concerns;

use Nemesis\Http\Request;
use Nemesis\Http\Response;

/**
 * Exceptions implementing this interface control their own HTTP response rendering.
 * ErrorHandler calls render() instead of generating a generic error view.
 */
interface RenderableException
{
    public function render(Request $request): Response;
}
