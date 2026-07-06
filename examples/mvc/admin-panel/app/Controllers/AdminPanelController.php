<?php

namespace App\Controllers;

use Nemesis\Core\Controller;

class AdminPanelController extends Controller
{
    public function index()
    {
        return $this->render('admin-panel.index', [
            'sections' => ['Users', 'Roles', 'Settings'],
        ]);
    }
}

