<?php

namespace App\Controllers;

use Nemesis\Core\Controller;

class EcommerceController extends Controller
{
    public function index()
    {
        return $this->render('ecommerce.index', [
            'products' => ['Starter Tee', 'Starter Hoodie', 'Starter Mug'],
        ]);
    }
}

