<?php

namespace App\Http\Resources;

use Nemesis\Http\JsonResource;

class MetricResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'name' => $this->resource['name'] ?? null,
            'value' => $this->resource['value'] ?? null,
        ];
    }
}

