<?php
namespace App\Controllers;

use App\Models\User;
use JarirAhmed\AuthTokenMaker\AuthTokenMaker;
use Nemesis\Core\Controller;
use Nemesis\Core\Validator;
use JarirAhmed\HTTPResponse\HTTPResponse;
use Nemesis\Helpers\Helpers;

class UserController {

    public function login() {
        $data = Helpers::getInput();

        // Validate input data
        if (empty($data['email']) || empty($data['password'])) {
            HTTPResponse::badRequest();
            Helpers::json([
                'error' => true,
                'message' => 'Email and password are required.'
            ]);
            return;
        }
    
        $user = new User();
        
        // Get user by email
        $userData = $user->getByEmail($data['email']);
        
        if (!$userData) {
            HTTPResponse::unauthorized();
            Helpers::json([
                'error' => true,
                'message' => 'Invalid email or password.'
            ]);            
            return;
        }
    
        // Verify password
        if (!Helpers::passwordVerify($data['password'], $userData['password'])) {
            HTTPResponse::unauthorized();
            Helpers::json([
                'error' => true,
                'message' => 'Invalid email or password.'
            ]);
            return;
        }
    
        // Generate auth token
        $authToken = $user->generateAuthToken();
    
        // Update auth token in the database
        $updateResult = $user->updateAuthToken($userData['id'], $authToken);
        if (!$updateResult) {
            HTTPResponse::internalServerError();
            Helpers::json([
                'error' => true,
                'message' => 'Failed to update auth token.'
            ]);
            return;
        }
    
        // Return successful login response
        Helpers::json([
            'success' => true,
            'message' => 'Login successful.',
            'auth_token' => $authToken,
            'user' => [
                'id' => $userData['id'],
                'email' => $userData['email']
            ]
        ]);
    }
    

    public function logout() {
        Helpers::json([
            'success' => true,
            'message' => 'Logout successful.'
        ], 200, true); // Pass `true` to enable pretty print
    }

    //Working 100%
    public function register() {
        $data = Helpers::getInput();

        //$validator = new \Nemesis\Core\Validator(); //Fully Classified Namespace Name

        $validator = new Validator();

        $rules = [
            'email' => 'required|email',
            'password' => 'required|min:6'
        ];
    
        if (!$validator->validate($data, $rules)) {
            HTTPResponse::badRequest();
            Helpers::json([
                'error' => true,
                'message' => 'Validation failed',
                'details' => $validator->errors()
            ]);
            return;
        }

        $user = new User();
        
        // Check if user already exists by email
        $existingUser = $user->getByEmail($data['email']);
        if ($existingUser) {
            HTTPResponse::badRequest(); // Return 400 if user already exists
            Helpers::json([
                'error' => true,
                'message' => 'User already exists with this email.'
            ]);
            return;
        }
        
        // Create new user and generate auth token
        $authTokenMaker = new AuthTokenMaker();
        do {
            $authToken = $authTokenMaker->generate(60); // Ensure the token is 60 characters
            // Check if the generated token already exists in the database
            $existingToken = $user->getByAuthToken($authToken);
        } while ($existingToken || empty($authToken)); // Repeat the generation if the token already exists or is empty
        
        // Create user with email, password, and unique auth token
        $user->create($data['email'], $data['password'], $authToken);
        
        Helpers::json([
            'message' => 'User registered successfully.',
            'auth_token' => $authToken
        ]);
    }
    
}
