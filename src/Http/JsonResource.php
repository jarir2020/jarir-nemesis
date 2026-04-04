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
}
