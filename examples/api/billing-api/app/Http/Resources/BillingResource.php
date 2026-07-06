<?php

namespace App\Http\Resources;

use Nemesis\Http\JsonResource;

class BillingResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'plan' => $this->resource['plan'] ?? null,
            'invoices' => $this->resource['invoices'] ?? null,
        ];
    }
}

