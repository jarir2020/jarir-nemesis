<?php

namespace App\Modules\Ecommerce\Controllers;

use App\Modules\Ecommerce\Models\Product;
use Nemesis\Core\Controller;

class EcommerceController extends Controller
{
    public function index()
    {
        return $this->render('ecommerce::index', [
            'products' => Product::all(),
        ]);
    }
}

