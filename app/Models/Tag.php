<?php
namespace App\Models;

use Nemesis\Core\Model;

class Tag extends Model {
    protected $table = 'tags';

    public function posts() {
        return $this->belongsToMany(Post::class, 'post_tag', 'tag_id', 'post_id');
    }
}
