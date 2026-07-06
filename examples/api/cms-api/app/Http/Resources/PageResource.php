<?php

namespace App\Http\Resources;

use Nemesis\Http\JsonResource;

class PageResource extends JsonResource
{
    public function toArray(): array
    {
        return ['title' => $this->resource['title'] ?? null];
    }
}

