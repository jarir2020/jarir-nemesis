<?php

namespace App\Controllers;

use Nemesis\Core\Controller;
use Nemesis\Http\ApiResponse;

class CmsApiController extends Controller
{
    public function pages()
    {
        return ApiResponse::success([
            ['title' => 'Home'],
            ['title' => 'About'],
        ]);
    }
}

