<?php
declare(strict_types=1);

namespace Nemesis\Auth;

use Nemesis\Core\Database;
use Nemesis\Services\Mailer;
use Nemesis\Core\Config;

class AccountVerification {
    protected $mailer;

    public function __construct() {
        $this->mailer = new Mailer();
    }

    public function sendVerificationLink($user) {
        $token = bin2hex(random_bytes(32));
        
        // Update user with verification token
        Database::table('users')->where('id', '=', $user['id'])->update([
            'verification_token' => $token
        ]);

        $link = Config::get('APP_URL') . "/verify-email?token={$token}";
        $subject = 'Verify Your Email Address';
        $body = "<h1>Email Verification</h1><p>Please verify your email via the link below:</p><a href='{$link}'>{$link}</a>";

        return $this->mailer->send($user['email'], $subject, $body);
    }

    public function verify($token) {
        $user = Database::table('users')
            ->where('verification_token', '=', $token)
            ->first();

        if (!$user) {
            return false;
        }

        // Mark as verified
        Database::table('users')->where('id', '=', $user['id'])->update([
            'email_verified_at' => date('Y-m-d H:i:s'),
            'verification_token' => null
        ]);

        return true;
    }
}
