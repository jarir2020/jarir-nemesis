<?php

namespace App\Modules\Shop\Models;

use Nemesis\Core\Model;

class Product extends Model
{
    protected $table = 'products';
    protected $fillable = ['name', 'price', 'sku'];
}

