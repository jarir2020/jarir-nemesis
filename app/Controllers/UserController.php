<?php
namespace App\Controllers;

use App\Models\User;
use JarirAhmed\AuthTokenMaker\AuthTokenMaker;
use Nemesis\Core\Controller;
use Nemesis\Core\Validator;
use JarirAhmed\HTTPResponse\HTTPResponse;
use JarirAhmed\TimeHelper\TimeHelper;
use Nemesis\Helpers\Helpers;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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


    public function sendResetOtp() {
        $data = Helpers::getInput();
        $email = $data['email'] ?? '';

        if (empty($email)) {
            HTTPResponse::badRequest();
            Helpers::json(['error' => true, 'message' => 'Email is required']);
            return;
        }

        $user = new User();
        $userData = $user->getByEmail($email);

        if (!$userData) {
            HTTPResponse::notFound();
            Helpers::json(['error' => true, 'message' => 'User not found']);
            return;
        }

        $otp = TimeHelper::generateRandomNumber(6);
        $user->storeOtp($userData['id'], $otp);

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'slot.book.wims@gmail.com';
            $mail->Password = 'ceib criu fpyf hpoh';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('slot.book.wims@gmail.com', 'Password Reset');
            $mail->addAddress($email);
            $mail->Subject = 'Your OTP for Password Reset';
            $mail->Body = "Your OTP is: $otp";

            $mail->send();
        } catch (Exception $e) {
            HTTPResponse::internalServerError();
            Helpers::json(['error' => true, 'message' => 'Failed to send OTP.']);
            return;
        }

        Helpers::json(['success' => true, 'message' => 'OTP sent to email']);
    }

    public function resetPassword() {
        $data = Helpers::getInput();
        $email = $data['email'] ?? '';
        $otp = $data['otp'] ?? '';
        $newPassword = $data['new_password'] ?? '';

        if (empty($email) || empty($otp) || empty($newPassword)) {
            HTTPResponse::badRequest();
            Helpers::json(['error' => true, 'message' => 'Email, OTP, and new password are required']);
            return;
        }

        $user = new User();
        $userData = $user->getByEmail($email);

        if (!$userData || $userData['otp'] !== $otp) {
            HTTPResponse::unauthorized();
            Helpers::json(['error' => true, 'message' => 'Invalid OTP or email']);
            return;
        }

        $user->updatePassword($userData['id'], $newPassword);
        $user->clearOtp($userData['id']);

        Helpers::json(['success' => true, 'message' => 'Password reset successful']);
    }
    
}
