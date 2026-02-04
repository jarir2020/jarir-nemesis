<?php

namespace App\Models;

use Nemesis\Core\Model;

class Post extends Model {
    protected $table = 'posts';

    public function comments() {
        return $this->hasMany(Comment::class, 'post_id', 'id');
    }

    public function author() {
        return $this->belongsTo(UserModel::class, 'user_id', 'id');
    }

    public function tags() {
        return $this->belongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id');
    }
}
