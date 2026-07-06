<?php

namespace App\Controllers;

use Nemesis\Core\Controller;
use Nemesis\Http\ApiResponse;

class UserApiController extends Controller
{
    public function index()
    {
        return ApiResponse::success([
            ['id' => 1, 'name' => 'Ada Lovelace'],
            ['id' => 2, 'name' => 'Grace Hopper'],
        ]);
    }

    public function show(int $id)
    {
        return ApiResponse::success(['id' => $id, 'name' => 'Example User']);
    }
}

