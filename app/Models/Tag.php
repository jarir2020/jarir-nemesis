<?php
namespace App\Models;

use Nemesis\Core\Fluent;

class Tag extends Fluent {
    public function __construct() {
        parent::__construct('tags');
    }
}
