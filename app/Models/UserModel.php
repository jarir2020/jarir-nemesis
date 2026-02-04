<?php

namespace App\Models;

use Nemesis\Core\Model;

class UserModel extends Model {
    protected $table = 'users';

    public function posts() {
        return $this->hasMany(Post::class, 'user_id', 'id');
    }

    public function comments() {
        return $this->hasMany(Comment::class, 'user_id', 'id');
    }
}
