<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Nemesis\Auth\MicroserviceBridge;
use Nemesis\Media\Attachment;
use Nemesis\Media\MediaLibrary;
use Nemesis\Notifications\Channels\SmsChannel;
use Nemesis\Notifications\Notification;
use Nemesis\Notifications\NotificationManager;
use Nemesis\Notifications\Notifiable;
use Nemesis\Testing\TestCase;

class Phase5User
{
    use Notifiable;

    public string $phone = '+15551234567';
    public string $email = 'user@example.com';
}

class Phase5SmsNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['sms'];
    }

    public function toSms(object $notifiable): array
    {
        return [
            'message' => 'Your code is 123456',
        ];
    }
}

class Phase5IntegrationTest extends TestCase
{
    public function setUp(): void
    {
        NotificationManager::reset();
        MediaLibrary::reset();
        MicroserviceBridge::reset();
    }

    public function testSmsChannelCapturesNotifications(): void
    {
        SmsChannel::fake();

        $user = new Phase5User();
        $user->notify(new Phase5SmsNotification());

        $this->assertTrue(SmsChannel::assertSentTo('+15551234567'));
        $this->assertSame('Your code is 123456', SmsChannel::lastSent()['message']);
    }

    public function testMicroserviceBridgeUsesInjectedTransport(): void
    {
        MicroserviceBridge::configure([
            'base_url' => 'https://auth.example.test',
            'token' => 'token-123',
        ]);

        MicroserviceBridge::setTransport(function (string $action, array $payload, array $config): array {
            return [
                'action' => $action,
                'payload' => $payload,
                'base_url' => $config['base_url'],
            ];
        });

        $auth = MicroserviceBridge::authenticate(['email' => 'user@example.com', 'password' => 'secret']);
        $profile = MicroserviceBridge::profile('access-token');

        $this->assertSame('authenticate', $auth['action']);
        $this->assertSame('https://auth.example.test', $auth['base_url']);
        $this->assertSame('access-token', $profile['payload']['access_token']);
    }

    public function testMediaLibraryReplaceAndDeleteManyWork(): void
    {
        $first = MediaLibrary::store([
            'filename' => 'first.jpg',
            'original_name' => 'first.jpg',
            'mime_type' => 'image/jpeg',
            'path' => 'uploads/first.jpg',
        ]);
        $second = MediaLibrary::store([
            'filename' => 'second.jpg',
            'original_name' => 'second.jpg',
            'mime_type' => 'image/jpeg',
            'path' => 'uploads/second.jpg',
        ]);

        $this->assertInstanceOf(Attachment::class, $first);
        $this->assertInstanceOf(Attachment::class, $second);
        $this->assertCount(2, MediaLibrary::all());

        $replacement = MediaLibrary::replace($first, [
            'name' => 'replacement.png',
            'type' => 'image/png',
            'size' => 2048,
            'tmp_name' => '',
            'error' => UPLOAD_ERR_OK,
        ]);

        $this->assertInstanceOf(Attachment::class, $replacement);
        $this->assertCount(2, MediaLibrary::all());

        $deleted = MediaLibrary::deleteMany([$replacement, $second]);
        $this->assertSame(2, $deleted);
        $this->assertCount(0, MediaLibrary::all());
    }
}

$test = new Phase5IntegrationTest();

echo "--- Phase 5 Integration Test ---\n";

foreach ([
    'testSmsChannelCapturesNotifications',
    'testMicroserviceBridgeUsesInjectedTransport',
    'testMediaLibraryReplaceAndDeleteManyWork',
] as $method) {
    echo "Running {$method}... ";
    try {
        $test->setUp();
        $test->{$method}();
        $test->tearDown();
        echo "PASS\n";
    } catch (\Throwable $e) {
        echo "FAIL: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n--- Phase 5 Integration Test Complete ---\n";
