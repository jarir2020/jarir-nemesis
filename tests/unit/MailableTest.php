<?php
declare(strict_types=1);

use Nemesis\Mail\Mailable;
use Nemesis\Services\Mailer;
use Nemesis\Queue\Drivers\SyncDriver;
use Nemesis\Queue\Queue;
use Nemesis\Core\View;
use Nemesis\Testing\TestCase;

class Phase4WelcomeMailable extends Mailable
{
    public function build(): static
    {
        return $this
            ->subject('Welcome')
            ->view('phase4.welcome', ['name' => '<Alice>']);
    }
}

class Phase4RawMailable extends Mailable
{
    public function build(): static
    {
        return $this
            ->subject('Raw message')
            ->html('<strong>Hello</strong>')
            ->text('Hello');
    }
}

class MailableTest extends TestCase
{
    private string $viewDirectory;

    public function setUp(): void
    {
        Mailer::fake();
        Queue::setDriver(new SyncDriver());

        $this->viewDirectory = sys_get_temp_dir() . '/nemesis-mail-' . uniqid('', true);
        mkdir($this->viewDirectory . '/phase4', 0755, true);
        file_put_contents(
            $this->viewDirectory . '/phase4/welcome.blade.php',
            '<h1>Welcome {{ $name }}</h1>'
        );
        View::addPath($this->viewDirectory);
    }

    public function tearDown(): void
    {
        Mailer::resetFake();
        @unlink($this->viewDirectory . '/phase4/welcome.blade.php');
        @rmdir($this->viewDirectory . '/phase4');
        @rmdir($this->viewDirectory);
    }

    public function testMailableRendersThroughNemesisViewEngineAndFakeDelivery(): void
    {
        $mailable = (new Phase4WelcomeMailable())->to('alice@example.com');
        $mailer = new Mailer();

        $this->assertTrue($mailer->send($mailable));
        $this->assertTrue(Mailer::assertSentTo('alice@example.com', 'Welcome'));

        $message = Mailer::sent()[0];
        $this->assertStringContainsString('Welcome &lt;Alice&gt;', $message['html']);
        $this->assertStringContainsString('Welcome &lt;Alice&gt;', $message['text']);
        $this->assertSame(Phase4WelcomeMailable::class, $message['mailable']);
    }

    public function testPendingMailAppliesRecipientsAndAttachments(): void
    {
        $attachment = tempnam(sys_get_temp_dir(), 'nemesis-mail-');
        file_put_contents($attachment, 'phase4 attachment');

        $mailer = new Mailer();
        $message = new Phase4RawMailable();
        $message->attach($attachment, ['as' => 'report.txt', 'mime' => 'text/plain']);

        $this->assertTrue($mailer
            ->to(['alice@example.com' => 'Alice'])
            ->cc('copy@example.com')
            ->bcc('blind@example.com')
            ->send($message));

        $sent = Mailer::sent()[0];
        $this->assertSame('Alice', $sent['to']['alice@example.com']);
        $this->assertArrayHasKey('copy@example.com', $sent['cc']);
        $this->assertArrayHasKey('blind@example.com', $sent['bcc']);
        $this->assertSame('report.txt', $sent['attachments'][0]['name']);

        @unlink($attachment);
    }

    public function testMailableCanUseTheExistingQueue(): void
    {
        $mailable = (new Phase4RawMailable())->to('queued@example.com');
        $mailer = new Mailer();

        $this->assertTrue($mailable->queue());
        $this->assertTrue(Mailer::assertSentTo('queued@example.com', 'Raw message'));
    }

    public function testInvalidMailHeadersAndRecipientsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Phase4RawMailable())->subject("Injected\r\nBcc: attacker@example.com");
    }

    public function testUnreadableAttachmentIsRejectedBeforeDelivery(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $mailable = (new Phase4RawMailable())->to('alice@example.com');
        $mailable->attach('/definitely/not/a/readable/file.txt');
        (new Mailer())->send($mailable);
    }
}
