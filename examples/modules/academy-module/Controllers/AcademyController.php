<?php

namespace App\Modules\Academy\Controllers;

use App\Modules\Academy\Models\Lesson;
use Nemesis\Core\Controller;

class AcademyController extends Controller
{
    public function index()
    {
        return $this->render('academy::index', [
            'lessons' => Lesson::all(),
        ]);
    }
}

