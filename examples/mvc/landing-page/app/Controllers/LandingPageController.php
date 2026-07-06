<?php

namespace App\Controllers;

use Nemesis\Core\Controller;

class LandingPageController extends Controller
{
    public function index()
    {
        return $this->render('landing-page.index', [
            'headline' => 'Launch faster with Nemesis',
            'cta' => 'Start building today',
        ]);
    }
}

