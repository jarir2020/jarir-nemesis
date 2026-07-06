<?php

namespace App\Modules\Shop\Controllers;

use App\Modules\Shop\Models\Product;
use Nemesis\Core\Controller;

class ShopController extends Controller
{
    public function index()
    {
        return $this->render('shop::index', [
            'products' => Product::all(),
        ]);
    }
}

