<?php

namespace App\Modules\Academy\Models;

use Nemesis\Core\Model;

class Lesson extends Model
{
    protected $table = 'lessons';
    protected $fillable = ['title', 'summary', 'sequence'];
}

