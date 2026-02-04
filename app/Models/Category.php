<?php
namespace App\Models;

use Nemesis\Core\Fluent;

class Category extends Fluent {
    public function __construct() {
        parent::__construct('categorys');
    }
}
