<?php

namespace App\Modules\Blog\Models;

use Nemesis\Core\Model;

class Post extends Model
{
    protected $table = 'posts';
    protected $fillable = ['title', 'slug', 'body'];
}

