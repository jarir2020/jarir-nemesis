<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Core\Validator;

function test($name, $callback) {
    echo "Testing $name... ";
    try {
        $callback();
        echo "✅ Passed\n";
    } catch (Exception $e) {
        echo "❌ Failed: " . $e->getMessage() . "\n";
        exit(1);
    }
}

function assertValid($data, $rules, $message = "Validation should pass") {
    $validator = new Validator();
    if (!$validator->validate($data, $rules)) {
        throw new Exception("$message. Errors: " . json_encode($validator->errors()));
    }
}

function assertInvalid($data, $rules, $expectedErrorField = null, $message = "Validation should fail") {
    $validator = new Validator();
    if ($validator->validate($data, $rules)) {
        throw new Exception($message . " Data: " . json_encode($data) . " Rules: " . json_encode($rules));
    }
    if ($expectedErrorField && !isset($validator->errors()[$expectedErrorField])) {
        throw new Exception("Expected error for field '$expectedErrorField' but got: " . json_encode($validator->errors()));
    }
}

echo "Starting Validator Tests...\n\n";

// 1. Required
test("Required Rule", function() {
    assertValid(['name' => 'John'], ['name' => 'required']);
    assertValid(['age' => 0], ['age' => 'required']); // 0 is valid
    assertValid(['flag' => '0'], ['flag' => 'required']); // '0' is valid
    
    assertInvalid(['name' => ''], ['name' => 'required'], 'name');
    assertInvalid(['name' => null], ['name' => 'required'], 'name');
});

// 2. Types
test("Type Rules", function() {
    // String
    assertValid(['name' => 'John'], ['name' => 'string']);
    assertInvalid(['name' => 123], ['name' => 'string'], 'name');

    // Integer
    assertValid(['age' => 25], ['age' => 'integer']);
    assertValid(['age' => '25'], ['age' => 'integer']); // String int is valid via filter_var
    assertInvalid(['age' => 'abc'], ['age' => 'integer'], 'age');
    assertInvalid(['age' => 25.5], ['age' => 'integer'], 'age');

    // Numeric
    assertValid(['price' => 10.5], ['price' => 'numeric']);
    assertValid(['price' => '10.5'], ['price' => 'numeric']);
    assertInvalid(['price' => 'abc'], ['price' => 'numeric'], 'price');

    // Boolean
    assertValid(['is_active' => true], ['is_active' => 'boolean']);
    assertValid(['is_active' => 1], ['is_active' => 'boolean']);
    assertValid(['is_active' => '0'], ['is_active' => 'boolean']);
    assertInvalid(['is_active' => 'yes'], ['is_active' => 'boolean'], 'is_active');

    // Array
    assertValid(['tags' => ['ph', 'js']], ['tags' => 'array']);
    assertInvalid(['tags' => 'php, js'], ['tags' => 'array'], 'tags');
});

// 3. Formats
test("Format Rules", function() {
    // Email
    assertValid(['email' => 'test@example.com'], ['email' => 'email']);
    assertInvalid(['email' => 'test@example'], ['email' => 'email'], 'email');

    // URL
    assertValid(['site' => 'https://google.com'], ['site' => 'url']);
    assertInvalid(['site' => 'google.com'], ['site' => 'url'], 'site'); // Needs protocol

    // Alpha / AlphaNum
    assertValid(['user' => 'JohnDoe'], ['user' => 'alpha']);
    assertInvalid(['user' => 'John Doe'], ['user' => 'alpha'], 'user'); // Space not allowed
    assertInvalid(['user' => 'John123'], ['user' => 'alpha'], 'user');
    
    assertValid(['user' => 'John123'], ['user' => 'alpha_num']);
    assertInvalid(['user' => 'John_Doe'], ['user' => 'alpha_num'], 'user'); // Underscore not allowed in ctype_alnum
});

// 4. Size (Min/Max/Between)
test("Size Rules", function() {
    // Numeric
    assertValid(['val' => 10], ['val' => 'min:5|max:15']);
    assertInvalid(['val' => 4], ['val' => 'min:5'], 'val');
    assertInvalid(['val' => 16], ['val' => 'max:15'], 'val');
    assertValid(['val' => 10], ['val' => 'between:5,15']);
    assertInvalid(['val' => 20], ['val' => 'between:5,15'], 'val');

    // String
    assertValid(['str' => 'hello'], ['str' => 'min:3|max:10']);
    assertInvalid(['str' => 'hi'], ['str' => 'min:3'], 'str');
    assertValid(['str' => 'hello'], ['str' => 'between:3,10']);

    // Array
    assertValid(['arr' => [1, 2, 3]], ['arr' => 'min:2|max:4']);
    assertInvalid(['arr' => [1]], ['arr' => 'min:2'], 'arr');
});

// 5. Regex
test("Regex Rule", function() {
    assertValid(['code' => 'ABC-123'], ['code' => 'regex:/^[A-Z]{3}-\d{3}$/']);
    assertInvalid(['code' => 'abc-123'], ['code' => 'regex:/^[A-Z]{3}-\d{3}$/'], 'code');
});

// 6. Confirmed
test("Confirmed Rule", function() {
    $data = [
        'password' => 'secret',
        'password_confirmation' => 'secret'
    ];
    assertValid($data, ['password' => 'confirmed']);

    $badData = [
        'password' => 'secret',
        'password_confirmation' => 'wrong'
    ];
    assertInvalid($badData, ['password' => 'confirmed'], 'password');
});

// 7. Custom Messages
test("Custom Messages", function() {
    $validator = new Validator();
    $validator->setMessages([
        'name.required' => 'NAME_REQUIRED_CUSTOM'
    ]);
    
    if ($validator->validate(['name' => ''], ['name' => 'required'])) {
        throw new Exception("Validation should have failed for custom message test");
    }
    
    $errors = $validator->errors();
    if ($errors['name'][0] !== 'NAME_REQUIRED_CUSTOM') {
        throw new Exception("Custom message not applied. Got: " . $errors['name'][0]);
    }
});

echo "\nAll validator tests passed successfully!\n";
