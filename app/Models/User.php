<?php
namespace App\Models;

use Nemesis\Core\Fluent;
use JarirAhmed\AuthTokenMaker\AuthTokenMaker;
use PDO;

class User {

    protected $table = 'users'; // Define the table name

    // Create a new user
    public function create($email, $password, $authToken) {
        // Hash the password before saving
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        // Using Fluent's insert method
        Fluent::table($this->table)->insert([
            'email' => $email,
            'password' => $hashedPassword,
            'auth_token' => $authToken
        ]);
    
        //return ['message' => 'User created successfully.'];
    }

    // Get user by email (for login)
    public function getByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = :email";
        $pdo = \Nemesis\Core\Database::connect();
        
        if ($pdo === null) {
            throw new \Exception("No database connection.");
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
 
        return $result ? $result : null;
    }
    


    // Update the user's auth token
    public function updateAuthToken($userId, $authToken) {
        
        Fluent::table($this->table)->whereUpdate('id', '=', $userId)->update([
            'auth_token' => $authToken
        ]);
    
        return ['message' => 'Auth token updated successfully.'];
    }

    // Get user by auth token
    public function getByAuthToken($authToken) {
        // Using Fluent's select method with proper parameter binding
        $result = Fluent::table($this->table)
                        ->whereGET('auth_token', '=', $authToken)  // Correct usage
                        ->first();  // Fetches the first record
        
        return $result ? $result : null;
    }

    // Generate and return a new auth token for the user
    public function generateAuthToken() {
        // Instantiate the AuthTokenMaker class
        $authTokenMaker = new AuthTokenMaker();
        
        // Generate a new token of 60 characters
        return $authTokenMaker->generate(60);
    }
}
