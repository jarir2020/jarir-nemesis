<?php

namespace App\Models;

use Nemesis\Core\Model;

class Comment extends Model {
    protected $table = 'comments';

    public function post() {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }

    public function author() {
        return $this->belongsTo(UserModel::class, 'user_id', 'id');
    }
}
