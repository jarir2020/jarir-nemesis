<?php

namespace App\Controllers;

use Nemesis\Core\Controller;
use Nemesis\Http\ApiResponse;

class ContentApiController extends Controller
{
    public function index()
    {
        return ApiResponse::success([
            ['id' => 1, 'title' => 'Launching Nemesis'],
            ['id' => 2, 'title' => 'Building faster with examples'],
        ]);
    }

    public function store()
    {
        return ApiResponse::success(['created' => true], 'Article created', 201);
    }
}
