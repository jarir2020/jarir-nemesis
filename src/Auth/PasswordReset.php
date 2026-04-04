<?php
declare(strict_types=1);

namespace Nemesis\Auth;

use Nemesis\Core\Database;
use Nemesis\Services\Mailer;
use Nemesis\Core\Config;

class PasswordReset {
    protected $mailer;
    protected $table = 'password_resets';

    public function __construct() {
        $this->mailer = new Mailer();
    }

    public function sendResetLink($email) {
        $token = bin2hex(random_bytes(32));
        
        // Save token to DB
        Database::table($this->table)->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Send Email
        $resetLink = Config::get('APP_URL') . "/password/reset?token={$token}&email=" . urlencode($email);
        $subject = 'Reset Your Password';
        $body = "<h1>Password Reset</h1><p>Click the link below to reset your password:</p><a href='{$resetLink}'>{$resetLink}</a>";

        return $this->mailer->send($email, $subject, $body);
    }

    public function reset($email, $token, $newPassword) {
        // Verify token
        $record = Database::table($this->table)
            ->where('email', '=', $email)
            ->where('token', '=', $token)
            ->first();

        if (!$record) {
            return false;
        }

        // Check if token is expired (e.g., 1 hour)
        $createdAt = strtotime($record['created_at']);
        if (time() - $createdAt > 3600) {
            $this->deleteToken($email);
            return false;
        }

        // Update User Password
        $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
        Database::table('users')->where('email', '=', $email)->update(['password' => $hashed]);

        // Delete used token
        $this->deleteToken($email);

        return true;
    }

    protected function deleteToken($email) {
        Database::table($this->table)->where('email', '=', $email)->delete();
    }
}
