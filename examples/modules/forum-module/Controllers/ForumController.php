<?php

namespace App\Modules\Forum\Controllers;

use App\Modules\Forum\Models\Thread;
use Nemesis\Core\Controller;

class ForumController extends Controller
{
    public function index()
    {
        return $this->render('forum::index', [
            'threads' => Thread::all(),
        ]);
    }
}

