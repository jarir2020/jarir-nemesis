<?php

namespace App\Extensions\PaymentAdapter;

class PaymentAdapter
{
    public function charge(int $amount): array
    {
        return ['amount' => $amount, 'status' => 'pending'];
    }
}

