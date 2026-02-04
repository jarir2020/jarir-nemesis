<?php
namespace App\Policies;

use Nemesis\Auth\Policy;

class PostPolicy extends Policy {
    public function view($user, $model) {
        return true;
    }

    public function update($user, $model) {
        return false;
    }
}
