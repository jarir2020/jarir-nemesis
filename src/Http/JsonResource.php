<?php
declare(strict_types=1);

namespace Nemesis\Http;

abstract class JsonResource {
    public $resource;

    public function __construct($resource) {
        $this->resource = $resource;
    }

    public static function collection($resource) {
        // Keep the historical array return type for existing callers while
        // delegating item mapping and paginator handling to the typed object.
        return static::resourceCollection($resource)->toArray();
    }

    /**
     * Create a typed collection object without changing collection() callers.
     */
    public static function resourceCollection($resource): ResourceCollection
    {
        return new ResourceCollection($resource, static::class);
    }

    public static function make($resource) {
        return new static($resource);
    }

    public function toArray() {
        if (method_exists($this->resource, 'toArray')) {
            return $this->resource->toArray();
        }
        return (array) $this->resource;
    }

    public function toJson() {
        return json_encode($this->toArray());
    }

    public function response(string $message = 'Success', int $status = 200): Response
    {
        return ResourceResponse::success($this, $message, $status);
    }

    public function created(string $message = 'Created'): Response
    {
        return ResourceResponse::created($this, $message);
    }

    public function updated(string $message = 'Updated'): Response
    {
        return ResourceResponse::updated($this, $message);
    }

    public function deleted(string $message = 'Deleted'): Response
    {
        return ResourceResponse::deleted($message);
    }

    public static function collectionResponse($resource, string $message = 'Success', int $status = 200): Response
    {
        return static::resourceCollection($resource)->toResponse($message, $status);
    }

    public static function paginatedResponse(\Nemesis\Core\Paginator $paginator, string $message = 'Success'): Response
    {
        return static::resourceCollection($paginator)->toResponse($message);
    }
}
