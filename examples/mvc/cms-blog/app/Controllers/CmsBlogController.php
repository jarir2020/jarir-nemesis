<?php

namespace App\Controllers;

use Nemesis\Core\Controller;

class CmsBlogController extends Controller
{
    public function index()
    {
        return $this->render('cms-blog.index', [
            'posts' => ['Welcome post', 'Publishing tips'],
        ]);
    }
}

