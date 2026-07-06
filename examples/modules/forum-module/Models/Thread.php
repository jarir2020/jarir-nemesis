<?php

namespace App\Modules\Forum\Models;

use Nemesis\Core\Model;

class Thread extends Model
{
    protected $table = 'threads';
    protected $fillable = ['title', 'body', 'author_id'];
}

