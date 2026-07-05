<?php
namespace App\Controllers;

use App\Models\User;
use JarirAhmed\AuthTokenMaker\AuthTokenMaker;
use Nemesis\Core\Controller;
use Nemesis\Core\Validator;
use JarirAhmed\HTTPResponse\HTTPResponse;
use JarirAhmed\TimeHelper\TimeHelper;
use Nemesis\Helpers\Helpers;
use Nemesis\Http\Request;
use Nemesis\Http\Response;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class UserController {

    public function login(Request $request) {
        $data = $request->all();

        // Validate input data
        if (empty($data['email']) || empty($data['password'])) {
            HTTPResponse::badRequest();
            Helpers::json([
                'error' => true,
                'message' => 'Email and password are required.'
            ]);
            return;
        }
    
        // Get user by email
        $userData = $this->findUserByEmail($data['email']);
        
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
        $authToken = $this->generateAuthToken();
    
        // Update auth token in the database
        $updateResult = $this->persistAuthToken($userData['id'], $authToken);
        if (!$updateResult) {
            HTTPResponse::internalServerError();
            Helpers::json([
                'error' => true,
                'message' => 'Failed to update auth token.'
            ]);
            return;
        }
    
        $dashboardUrl = function_exists('route') ? route('dashboard.page') : '/dashboard';
        if ($dashboardUrl === '#' || $dashboardUrl === '') {
            $dashboardUrl = '/dashboard';
        }

        $accept = strtolower((string) $request->header('Accept', ''));
        $wantsHtml = str_contains($accept, 'text/html') || $accept === '';

        // Browser form submits should land on the dashboard page.
        if ($wantsHtml) {
            return Response::redirect($dashboardUrl);
        }

        return Response::json([
            'success' => true,
            'message' => 'Login successful.',
            'auth_token' => $authToken,
            'redirect_to' => $dashboardUrl,
            'user' => [
                'id' => $userData['id'],
                'email' => $userData['email']
            ]
        ]);
    }

    /**
     * Hook points for tests and future auth providers.
     */
    protected function findUserByEmail(string $email): ?array
    {
        $user = new User();
        return $user->getByEmail($email);
    }

    protected function persistAuthToken(int|string $userId, string $authToken): mixed
    {
        $user = new User();
        return $user->updateAuthToken($userId, $authToken);
    }

    protected function generateAuthToken(): string
    {
        $user = new User();
        return $user->generateAuthToken();
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
