<?php

namespace App\Controllers;

use Nemesis\Core\Controller;

class CommerceDashboardController extends Controller
{
    public function index()
    {
        return $this->render('commerce-dashboard.index', [
            'widgets' => ['Revenue', 'Orders', 'Products'],
        ]);
    }
}

