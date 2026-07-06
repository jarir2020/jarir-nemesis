<?php

namespace App\Controllers;

use Nemesis\Core\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return $this->render('dashboard.index', [
            'metrics' => [
                ['label' => 'Revenue', 'value' => '$120k'],
                ['label' => 'Users', 'value' => '8,240'],
            ],
        ]);
    }
}

