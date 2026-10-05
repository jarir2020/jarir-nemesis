<?php
declare(strict_types=1);

namespace Nemesis\Http;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Nemesis\Core\Paginator;
use Traversable;

/**
 * A typed collection of JsonResource instances.
 *
 * The collection keeps Nemesis' success/message/data response envelope while
 * adding a stable place for pagination metadata and additional top-level data.
 * It accepts arrays, Traversable collections, and Nemesis Paginator objects.
 *
 * @implements IteratorAggregate<int|string, JsonResource>
 */
class ResourceCollection implements IteratorAggregate, Countable, JsonSerializable
{
    /** @var array<int|string, mixed> */
    protected array $items;

    /** @var array<string, mixed> */
    protected array $additional = [];

    /**
     * @param class-string<JsonResource> $resourceClass
     */
    public function __construct(
        public mixed $resource,
        protected string $resourceClass = JsonResource::class,
    ) {
        if (!is_a($resourceClass, JsonResource::class, true)) {
            throw new \InvalidArgumentException("Resource class [{$resourceClass}] must extend " . JsonResource::class . '.');
        }

        $items = $resource instanceof Paginator ? $resource->items() : $resource;
        $this->items = $this->normalizeItems($items);
    }

    /**
     * Return the mapped resource payload without response metadata.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(?Request $request = null): array
    {
        $data = [];

        foreach ($this->items as $key => $item) {
            $data[$key] = $this->makeResource($item)->toArray();
        }

        return $data;
    }

    /**
     * Resolve data, paginator metadata, and additional top-level values.
     *
     * @return array<string, mixed>
     */
    public function resolve(?Request $request = null): array
    {
        $payload = ['data' => $this->toArray($request)];

        if ($this->resource instanceof Paginator) {
            $pagination = $this->resource->toArray();
            if (isset($pagination['meta']) && is_array($pagination['meta'])) {
                $payload['meta'] = $pagination['meta'];
            }
        }

        return array_merge($payload, $this->additional);
    }

    /**
     * Add top-level response metadata or other response values.
     *
     * @param array<string, mixed> $data
     */
    public function additional(array $data): static
    {
        $this->additional = array_merge($this->additional, $data);
        return $this;
    }

    /**
     * Create a standard Nemesis JSON response from the collection.
     */
    public function toResponse(string $message = 'Success', int $status = 200, ?Request $request = null): Response
    {
        return Response::json(array_merge(
            ['success' => true, 'message' => $message],
            $this->resolve($request),
        ), $status);
    }

    /** Alias for applications that use resource->response() conventions. */
    public function response(string $message = 'Success', int $status = 200, ?Request $request = null): Response
    {
        return $this->toResponse($message, $status, $request);
    }

    /**
     * Iterate over mapped JsonResource instances.
     *
     * @return Traversable<int|string, JsonResource>
     */
    public function getIterator(): Traversable
    {
        $resources = [];
        foreach ($this->items as $key => $item) {
            $resources[$key] = $this->makeResource($item);
        }

        return new ArrayIterator($resources);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function jsonSerialize(): mixed
    {
        return $this->resolve();
    }

    protected function makeResource(mixed $item): JsonResource
    {
        if ($item instanceof JsonResource) {
            return $item;
        }

        if ($this->resourceClass === JsonResource::class) {
            throw new \InvalidArgumentException(
                'A concrete JsonResource class is required when collection items are not already resources.'
            );
        }

        $resource = new $this->resourceClass($item);
        if (!$resource instanceof JsonResource) {
            throw new \InvalidArgumentException("Resource class [{$this->resourceClass}] did not create a JsonResource.");
        }

        return $resource;
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function normalizeItems(mixed $items): array
    {
        if (is_array($items)) {
            return $items;
        }

        if ($items instanceof Traversable) {
            return iterator_to_array($items);
        }

        if ($items === null) {
            return [];
        }

        if (is_object($items) && method_exists($items, 'toArray')) {
            $array = $items->toArray();
            if (is_array($array)) {
                return $array;
            }
        }

        return (array) $items;
    }
}
