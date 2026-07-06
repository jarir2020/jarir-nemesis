<?php

namespace App\Modules\Ecommerce\Models;

use Nemesis\Core\Model;

class Product extends Model
{
    protected $table = 'products';
    protected $fillable = ['name', 'price', 'sku'];
}

