<?php

namespace App\Controllers;

use App\Models\Post;
use Nemesis\Core\Controller;

class BlogController extends Controller
{
    public function index()
    {
        $posts = Post::published()->get();

        return $this->render('blog.index', ['posts' => $posts]);
    }

    public function show(string $slug)
    {
        $post = Post::where('slug', $slug)->first();

        return $this->render('blog.show', ['post' => $post]);
    }
}

