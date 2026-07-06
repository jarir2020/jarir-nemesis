<?php

namespace App\Controllers;

use Nemesis\Core\Controller;
use Nemesis\Http\ApiResponse;

class AnalyticsApiController extends Controller
{
    public function index()
    {
        return ApiResponse::success([
            ['name' => 'signups', 'value' => 120],
            ['name' => 'revenue', 'value' => 4200],
        ]);
    }
}

