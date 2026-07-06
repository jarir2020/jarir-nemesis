<?php

namespace App\Controllers;

use Nemesis\Core\Controller;
use Nemesis\Http\ApiResponse;

class BillingApiController extends Controller
{
    public function index()
    {
        return ApiResponse::success([
            'plan' => 'pro',
            'invoices' => 12,
            'balance' => 0,
        ]);
    }
}

