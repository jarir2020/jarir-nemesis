<?php

namespace App\Controllers;

use Nemesis\Core\Controller;
use Nemesis\Http\ApiResponse;

class LoginApiController extends Controller
{
    public function login()
    {
        return ApiResponse::success(['token' => 'demo-token'], 'Login successful');
    }
}

