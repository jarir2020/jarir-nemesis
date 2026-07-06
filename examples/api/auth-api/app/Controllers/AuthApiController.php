<?php

namespace App\Controllers;

use Nemesis\Core\Controller;
use Nemesis\Http\ApiResponse;

class AuthApiController extends Controller
{
    public function login()
    {
        return ApiResponse::success(['token' => 'example-token'], 'Logged in');
    }

    public function logout()
    {
        return ApiResponse::success(['logged_out' => true], 'Logged out');
    }
}

