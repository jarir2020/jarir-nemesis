<?php

namespace App\Http\Resources;

use Nemesis\Http\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource['id'] ?? null,
            'title' => $this->resource['title'] ?? null,
        ];
    }
}

