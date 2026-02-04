<?php

namespace App\Database\Factories;

use Nemesis\Database\Factories\Factory;
use App\Models\UserModel;

class UserModelFactory extends Factory {
    protected $model = UserModel::class;

    public function definition() {
        $unique = bin2hex(random_bytes(4));
        return [
            'name' => 'User ' . $unique,
            'email' => "user{$unique}@example.com",
            'password' => password_hash('password', PASSWORD_BCRYPT),
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
}
