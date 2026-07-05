<?php
declare(strict_types=1);

namespace Nemesis\Http;

abstract class JsonResource {
    public $resource;

    public function __construct($resource) {
        $this->resource = $resource;
    }

    public static function collection($resource) {
        return array_map(function($item) {
            return (new static($item))->toArray();
        }, is_array($resource) ? $resource : ($resource->toArray() ?? []));
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
        return ResourceResponse::success(static::collection($resource), $message, $status);
    }

    public static function paginatedResponse(\Nemesis\Core\Paginator $paginator, string $message = 'Success'): Response
    {
        return ResourceResponse::paginated($paginator, $message);
    }
}
