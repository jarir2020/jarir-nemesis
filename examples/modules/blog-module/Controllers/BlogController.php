<?php

namespace App\Modules\Blog\Controllers;

use App\Modules\Blog\Models\Post;
use Nemesis\Core\Controller;

class BlogController extends Controller
{
    public function index()
    {
        return $this->render('blog::index', [
            'posts' => Post::all(),
        ]);
    }
}

