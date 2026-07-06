<?php

namespace App\Modules\Cms\Models;

use Nemesis\Core\Model;

class Page extends Model
{
    protected $table = 'pages';
    protected $fillable = ['title', 'slug', 'body'];
}

