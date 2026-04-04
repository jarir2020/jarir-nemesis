<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 3 — Pipeline terminate() | Updated: 2026-04-02

namespace Nemesis\Http;

/**
 * Middleware pipeline.
 *
 * Usage:
 *   (new Pipeline())
 *       ->send($request)
 *       ->through($middlewareStack)
 *       ->then(fn($req) => $controller->action($req));
 *
 * After the response is sent, call terminate() to give terminable middleware
 * a chance to run post-response work (e.g. session flushing, logging).
 */
class Pipeline
{
    protected mixed $request    = null;
    protected array $middleware = [];

    // -------------------------------------------------------------------------
    // Builder
    // -------------------------------------------------------------------------

    public function send(mixed $request): static
    {
        $this->request = $request;
        return $this;
    }

    public function through(mixed $middleware): static
    {
        $this->middleware = is_array($middleware) ? $middleware : func_get_args();
        return $this;
    }

    // -------------------------------------------------------------------------
    // Execute — wraps the controller's output in a Response if needed
    // -------------------------------------------------------------------------

    public function then(\Closure $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            $this->carry(),
            $this->destination($destination)
        );

        return $pipeline($this->request);
    }

    // -------------------------------------------------------------------------
    // Terminable middleware — Phase 3
    //
    // After index.php calls $response->send(), call terminate() so middleware
    // that implement a terminate() method can run post-response logic
    // (e.g. session writes, access logs) without blocking the client.
    //
    // Example in Kernel dispatch loop:
    //   $response = $pipeline->then($destination);
    //   $response->send();
    //   $pipeline->terminate($request, $response);
    // -------------------------------------------------------------------------

    public function terminate(mixed $request, Response $response): void
    {
        foreach ($this->middleware as $pipe) {
            // Resolve string class names
            if (is_string($pipe)) {
                $pipe = new $pipe();
            }

            if (is_object($pipe) && method_exists($pipe, 'terminate')) {
                $pipe->terminate($request, $response);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    protected function carry(): \Closure
    {
        return function (callable $stack, mixed $pipe): \Closure {
            return function (mixed $request) use ($stack, $pipe): Response {
                if (is_string($pipe)) {
                    $pipe = new $pipe();
                }

                if (is_callable($pipe)) {
                    return $this->toResponse($pipe($request, $stack));
                }

                if (method_exists($pipe, 'handle')) {
                    return $this->toResponse($pipe->handle($request, $stack));
                }

                return $stack($request);
            };
        };
    }

    /**
     * The terminal step: call the controller action and wrap its return value
     * in a Response so every layer of the pipeline sees a Response.
     */
    protected function destination(\Closure $destination): \Closure
    {
        return function (mixed $request) use ($destination): Response {
            return $this->toResponse($destination($request));
        };
    }

    /**
     * Coerce any value returned by an action/middleware into a Response.
     * - Response instance  → returned as-is
     * - string / numeric   → wrapped in Response::make()
     * - null / void        → empty 200 response (controller echoed directly)
     */
    protected function toResponse(mixed $value): Response
    {
        if ($value instanceof Response) {
            return $value;
        }
        if (is_string($value) || is_numeric($value)) {
            return Response::make((string) $value);
        }
        return Response::make();
    }
}
