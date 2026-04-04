<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 4 — Attribute Routing | Created: 2026-04-02

namespace Nemesis\Attributes;

/**
 * #[Route] — PHP 8 attribute for declarative route registration.
 *
 * Usage on a controller method:
 *
 *   #[Route('GET', '/products/{id}', name: 'product.show', middleware: ['auth'])]
 *   public function show(Request $request, string $id): Response { ... }
 *
 * Then register the controller with the router:
 *   $router->scanAttributes(ProductController::class);
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        /** HTTP method: GET, POST, PUT, PATCH, DELETE, OPTIONS, ANY */
        public readonly string $method,

        /** URI pattern, e.g. '/products/{id}' */
        public readonly string $uri,

        /** Optional named route alias for the route() helper */
        public readonly ?string $name = null,

        /** Middleware aliases or class names to apply */
        public readonly array $middleware = [],
    ) {}
}
