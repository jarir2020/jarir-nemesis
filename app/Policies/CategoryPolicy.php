<?php
namespace App\Policies;

use Nemesis\Auth\Policy;

class CategoryPolicy extends Policy {
    public function view($user, $model) {
        return true;
    }

    public function update($user, $model) {
        return false;
    }
}
