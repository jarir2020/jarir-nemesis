<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Core\Validator;

echo "--- Validation Test ---\n";

$validator = new Validator();

// 1. Valid Data
$data = ['email' => 'test@example.com', 'age' => 25];
$rules = ['email' => 'required|email', 'age' => 'required|integer'];
echo "Testing Valid Data: ";
echo ($validator->validate($data, $rules) ? "PASS" : "FAIL") . "\n";

// 2. Invalid Data
$data = ['email' => 'invalid-email', 'age' => 'old'];
echo "Testing Invalid Data (Email): ";
$validator->validate($data, $rules);
$errors = $validator->errors();
echo (isset($errors['email']) ? "PASS" : "FAIL") . "\n";

echo "Testing Invalid Data (Integer): ";
echo (isset($errors['age']) ? "PASS" : "FAIL") . "\n";

echo "\n--- Validation Test Complete ---\n";
