<?php

namespace App\Controllers;

use Nemesis\Core\Controller;

class ProfileCenterController extends Controller
{
    public function index()
    {
        return $this->render('profile-center.index', [
            'name' => 'Example User',
        ]);
    }
}

