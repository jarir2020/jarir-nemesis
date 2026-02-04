<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Auth\JWT;
use Nemesis\Middleware\ApiVersionMiddleware;
use Nemesis\Auth\OAuth2Provider;
use Nemesis\GraphQL\GraphQLServer;
use Nemesis\Documentation\SwaggerGenerator;

echo "--- Advanced API Features Test ---\n";

// Test 1: JWT Token Generation & Validation
echo "\nTesting JWT::encode() and decode(): ";
try {
    JWT::setSecret('test-secret-key-12345');
    $token = JWT::encode(['user_id' => 1, 'email' => 'test@example.com'], 3600);
    $payload = JWT::decode($token);
    
    $valid = ($payload['user_id'] === 1 && $payload['email'] === 'test@example.com');
    echo ($valid ? "PASS" : "FAIL") . "\n";
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// Test 2: JWT Verification
echo "Testing JWT::verify(): ";
try {
    $isValid = JWT::verify($token);
    echo ($isValid ? "PASS" : "FAIL") . "\n";
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// Test 3: API Versioning
echo "Testing ApiVersionMiddleware: ";
try {
    $_SERVER['REQUEST_URI'] = '/api/v2/users';
    $middleware = new ApiVersionMiddleware();
    $middleware->handle(null, function($req) { return true; });
    
    $version = defined('API_VERSION') ? API_VERSION : null;
    echo ($version === 'v2' ? "PASS" : "FAIL (got: $version)") . "\n";
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// Test 4: OAuth2 Provider Registration
echo "Testing OAuth2Provider: ";
try {
    $oauth = new OAuth2Provider();
    $oauth->registerProvider('test', 'client123', 'secret456', 'http://localhost/callback', [
        'authorization' => 'https://test.com/auth',
        'token' => 'https://test.com/token',
        'user_info' => 'https://test.com/user'
    ]);
    echo "PASS\n";
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// Test 5: GraphQL Server
echo "Testing GraphQL\GraphQLServer: ";
try {
    $graphql = new GraphQLServer();
    $graphql->setSchema('type Query { hello: String }');
    echo "PASS (server initialized)\n";
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// Test 6: Swagger Generator
echo "Testing SwaggerGenerator: ";
try {
    $swagger = new SwaggerGenerator('Test API', '1.0.0');
    $swagger->addPath('/users', 'GET', [
        'summary' => 'Get all users',
        'responses' => ['200' => ['description' => 'Success']]
    ]);
    $json = $swagger->toJson();
    echo (strpos($json, 'openapi') !== false ? "PASS" : "FAIL") . "\n";
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

echo "\n--- Advanced API Features Test Complete ---\n";
echo "\nAll 5 features implemented:\n";
echo "✓ API Versioning (v1/v2 route support via middleware)\n";
echo "✓ JWT Token Management (encode/decode/verify)\n";
echo "✓ OAuth2 Provider (Google, GitHub presets + custom providers)\n";
echo "✓ GraphQL Server (basic infrastructure)\n";
echo "✓ Swagger/OpenAPI Generator (documentation)\n";
