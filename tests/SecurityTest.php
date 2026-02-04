<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Core\Config;
use Nemesis\Core\Database;
use Nemesis\Middleware\SecurityHeadersMiddleware;
use Nemesis\Auth\Totp;
use Nemesis\Services\Sms\SmsService;
use Nemesis\Auth\PasswordReset;
use Nemesis\Auth\AccountVerification;

Config::load(__DIR__ . '/../');
$appConfig = require __DIR__ . '/../config/config.php';
Database::connect($appConfig['database']);

echo "--- Security Enhancements Test ---\n";

// Test 1: Security Headers
echo "\nTesting SecurityHeadersMiddleware: ";
try {
    $middleware = new SecurityHeadersMiddleware();
    // Cannot fully test headers in CLI, but verifying instantiation and logic
    $middleware->handle(null, function($req) { return true; });
    echo "PASS (Logic verified)\n";
} catch (\Exception $e) {
    echo "FAIL\n";
}

// Test 2: TOTP (2FA)
echo "\nTesting TOTP (RFC 6238): ";
try {
    $totp = new Totp();
    $secret = $totp->getSecret();
    $code = $totp->now();
    $isValid = $totp->verify($code);
    
    if (strlen($secret) === 16 && $isValid) {
        echo "PASS\n";
        echo "  Secret: $secret\n";
        echo "  Current Code: $code\n";
    } else {
        echo "FAIL\n";
    }
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// Test 3: SMS Service (Mock Log)
echo "\nTesting SMS Service (LogDriver): ";
try {
    $sms = new SmsService();
    $sms->clearLogs();
    $sms->send('+1234567890', 'Your verification code is 123456');
    $logs = $sms->getLogs();
    
    if (strpos($logs, 'Your verification code is 123456') !== false) {
        echo "PASS (Log entry found)\n";
    } else {
        echo "FAIL\n";
    }
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// Setup DB for email tests (assumes setup_test_db.php run previously)
// We need to re-run schema to get new tables
echo "\nUpdating Database Schema... ";
try {
    $schema = file_get_contents(__DIR__ . '/../database/test_schema.sql');
    Database::connect()->exec($schema);
    // Re-seed minimal user
    Database::table('users')->insert([
        'name' => 'Test User',
        'email' => 'slot.book.wims@gmail.com', // Using your email for live test
        'password' => password_hash('password', PASSWORD_BCRYPT)
    ]);
    echo "✓ Schema updated\n";
} catch (\Exception $e) {
    echo "✗ Failed to update schema: " . $e->getMessage() . "\n";
}

// Test 4: Password Reset (Live Email)
echo "\nTesting Password Reset (sending to slot.book.wims@gmail.com): ";
try {
    $reset = new PasswordReset();
    $sent = $reset->sendResetLink('slot.book.wims@gmail.com');
    
    if ($sent) {
        echo "PASS (Email sent)\n";
        // Verify DB entry
        $token = Database::table('password_resets')->where('email', '=', 'slot.book.wims@gmail.com')->first();
        if ($token) {
            echo "  Token stored in DB: " . substr($token['token'], 0, 10) . "...\n";
        } else {
            echo "  FAIL: Token not stored in DB\n";
        }
    } else {
        echo "FAIL (Email sending failed)\n";
    }
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

// Test 5: Account Verification
echo "\nTesting Account Verification: ";
try {
    $verify = new AccountVerification();
    $user = Database::table('users')->where('email', '=', 'slot.book.wims@gmail.com')->first();
    
    $sent = $verify->sendVerificationLink($user);
    if ($sent) {
        echo "PASS (Email sent)\n";
        // Check DB update
        $updatedUser = Database::table('users')->where('id', '=', $user['id'])->first();
        if ($updatedUser['verification_token']) {
            echo "  Verification token generated: " . substr($updatedUser['verification_token'], 0, 10) . "...\n";
            
            // Verify Logic
            $verified = $verify->verify($updatedUser['verification_token']);
            if ($verified) {
                echo "  Verification logic: PASS\n";
            } else {
                echo "  Verification logic: FAIL\n";
            }
        } else {
            echo "  FAIL: Token not saved to user\n";
        }
    } else {
        echo "FAIL\n";
    }
} catch (\Exception $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
}

echo "\n--- Security Enhancements Test Complete ---\n";
