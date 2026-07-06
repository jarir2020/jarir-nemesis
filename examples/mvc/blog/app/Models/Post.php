<?php

namespace App\Models;

use Nemesis\Core\Model;

class Post extends Model
{
    protected $table = 'posts';
    protected $fillable = ['title', 'slug', 'body', 'status'];
}

