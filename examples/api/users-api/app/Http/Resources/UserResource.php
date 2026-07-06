<?php

namespace App\Http\Resources;

use Nemesis\Http\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource['id'] ?? null,
            'name' => $this->resource['name'] ?? null,
        ];
    }
}

