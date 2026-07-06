<?php

namespace App\Modules\Cms\Controllers;

use App\Modules\Cms\Models\Page;
use Nemesis\Core\Controller;

class CmsController extends Controller
{
    public function index()
    {
        return $this->render('cms::index', [
            'pages' => Page::all(),
        ]);
    }
}

