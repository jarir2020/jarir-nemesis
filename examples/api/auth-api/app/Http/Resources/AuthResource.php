<?php

namespace App\Http\Resources;

use Nemesis\Http\JsonResource;

class AuthResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'token' => $this->resource['token'] ?? null,
        ];
    }
}

