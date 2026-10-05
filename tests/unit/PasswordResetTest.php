<?php
declare(strict_types=1);

use Nemesis\Auth\PasswordReset;
use Nemesis\Core\Database;
use Nemesis\Services\Mailer;
use Nemesis\Testing\TestCase;

/** Mailer double that records the reset link without contacting SMTP. */
class Phase3RecordingMailer extends Mailer
{
    public array $messages = [];

    public function __construct() {}

    public function send($to, $subject = null, $body = null, $altBody = ''): bool
    {
        $this->messages[] = compact('to', 'subject', 'body', 'altBody');
        return true;
    }
}

class PasswordResetTest extends TestCase
{
    private const EMAIL = 'phase3@example.com';

    public function setUp(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        Database::setPdo($pdo);

        $pdo->exec('CREATE TABLE password_resets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            token TEXT NOT NULL,
            created_at DATETIME NOT NULL
        )');
        $pdo->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            password TEXT NOT NULL
        )');
        $pdo->exec("INSERT INTO users (email, password) VALUES ('" . self::EMAIL . "', 'original-password')");
    }

    public function testDatabaseStoresOnlyTokenDigest(): void
    {
        $mailer = new Phase3RecordingMailer();
        $reset = new PasswordReset($mailer, 'password_resets', 60);

        $this->assertTrue($reset->sendResetLink(self::EMAIL));
        $token = $this->tokenFromMessage($mailer);
        $record = Database::table('password_resets')->where('email', '=', self::EMAIL)->first();

        $this->assertNotNull($record);
        $this->assertSame(hash('sha256', $token), $record['token']);
        $this->assertNotSame($token, $record['token']);
        $this->assertSame(64, strlen($record['token']));
    }

    public function testRawDatabaseTokenIsNotAccepted(): void
    {
        $mailer = new Phase3RecordingMailer();
        $reset = new PasswordReset($mailer, 'password_resets', 60);
        $this->assertTrue($reset->sendResetLink(self::EMAIL));

        $token = $this->tokenFromMessage($mailer);
        Database::table('password_resets')
            ->where('email', '=', self::EMAIL)
            ->update(['token' => $token]);

        $this->assertFalse($reset->reset(self::EMAIL, $token, 'new-password'));
    }

    public function testExpiredTokenIsRemovedAndRejected(): void
    {
        $token = bin2hex(random_bytes(32));
        Database::table('password_resets')->insert([
            'email' => self::EMAIL,
            'token' => hash('sha256', $token),
            'created_at' => date('Y-m-d H:i:s', time() - 61 * 60),
        ]);

        $reset = new PasswordReset(new Phase3RecordingMailer(), 'password_resets', 60);
        $this->assertFalse($reset->reset(self::EMAIL, $token, 'new-password'));
        $this->assertNull(Database::table('password_resets')->where('email', '=', self::EMAIL)->first());
    }

    public function testSuccessfulResetConsumesTokenAndPreventsReplay(): void
    {
        $mailer = new Phase3RecordingMailer();
        $reset = new PasswordReset($mailer, 'password_resets', 60);
        $this->assertTrue($reset->sendResetLink(self::EMAIL));
        $token = $this->tokenFromMessage($mailer);

        $this->assertTrue($reset->reset(self::EMAIL, $token, 'new-password'));
        $this->assertFalse($reset->reset(self::EMAIL, $token, 'attacker-password'));
        $this->assertNull(Database::table('password_resets')->where('email', '=', self::EMAIL)->first());

        $user = Database::table('users')->where('email', '=', self::EMAIL)->first();
        $this->assertTrue(password_verify('new-password', $user['password']));
    }

    public function testIssuingAnotherTokenInvalidatesThePreviousOne(): void
    {
        $mailer = new Phase3RecordingMailer();
        $reset = new PasswordReset($mailer, 'password_resets', 60);

        $this->assertTrue($reset->sendResetLink(self::EMAIL));
        $firstToken = $this->tokenFromMessage($mailer, 0);
        $this->assertTrue($reset->sendResetLink(self::EMAIL));
        $secondToken = $this->tokenFromMessage($mailer, 1);

        $this->assertNotSame($firstToken, $secondToken);
        $this->assertSame(1, Database::table('password_resets')->where('email', '=', self::EMAIL)->count());
        $this->assertFalse($reset->reset(self::EMAIL, $firstToken, 'old-link-password'));
        $this->assertTrue($reset->reset(self::EMAIL, $secondToken, 'current-link-password'));
    }

    public function testResetTableIdentifierIsValidated(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PasswordReset(new Phase3RecordingMailer(), 'password_resets; DROP TABLE users', 60);
    }

    public function testInvalidExpiryIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PasswordReset(new Phase3RecordingMailer(), 'password_resets', 0);
    }

    public function testInvalidEmailDoesNotIssueAToken(): void
    {
        $mailer = new Phase3RecordingMailer();
        $reset = new PasswordReset($mailer, 'password_resets', 60);

        $this->assertFalse($reset->sendResetLink('not-an-email'));
        $this->assertSame(0, Database::table('password_resets')->count());
        $this->assertSame(0, count($mailer->messages));
    }

    private function tokenFromMessage(Phase3RecordingMailer $mailer, int $index = 0): string
    {
        $body = $mailer->messages[$index]['body'] ?? '';
        preg_match('/[?&]token=([a-f0-9]{64})/', $body, $matches);
        return $matches[1] ?? '';
    }
}
