<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Auth\Gate;
use Nemesis\Auth\Policy;

echo "--- Policy/Gate Test ---\n";

class TestPolicy extends Policy {
    public function update($user, $model) {
        return $user->id === $model->user_id;
    }
}

$user = (object)['id' => 1];
$model = (object)['user_id' => 1];
$wrongModel = (object)['user_id' => 2];

Gate::policy('test', TestPolicy::class);

echo "Testing Gate Allows (Same User): ";
echo (Gate::allows('update', 'test', $user, $model) ? "PASS" : "FAIL") . "\n";

echo "Testing Gate Denies (Wrong User): ";
echo (Gate::allows('update', 'test', $user, $wrongModel) === false ? "PASS" : "FAIL") . "\n";

echo "\n--- Policy/Gate Test Complete ---\n";
