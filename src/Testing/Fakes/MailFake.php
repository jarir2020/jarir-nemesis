<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 8 — Testing Infrastructure | Created: 2026-04-02

namespace Nemesis\Testing\Fakes;

/**
 * MailFake — captures sent mail instead of actually sending it.
 *
 * Usage:
 *   $mail = new MailFake();
 *   $mail->send('welcome', ['to' => 'alice@example.com', 'subject' => 'Hi']);
 *   $mail->assertSentTo('alice@example.com', 'welcome');
 */
class MailFake
{
    /** @var list<array<string, mixed>> */
    protected array $sent = [];

    // -------------------------------------------------------------------------
    // Capture
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $data  Must include at least 'to' and 'subject'
     */
    public function send(string $mailable, array $data = []): void
    {
        $this->sent[] = array_merge($data, ['mailable' => $mailable]);
    }

    // -------------------------------------------------------------------------
    // Assertions
    // -------------------------------------------------------------------------

    public function assertSent(string $mailable, ?callable $callback = null): void
    {
        $matching = array_filter($this->sent, fn($m) => $m['mailable'] === $mailable);
        if (empty($matching)) {
            throw new \Exception("Mail [{$mailable}] was not sent.");
        }
        if ($callback !== null) {
            foreach ($matching as $mail) {
                if ($callback($mail) === true) return;
            }
            throw new \Exception("Mail [{$mailable}] was sent but callback did not match.");
        }
    }

    public function assertNotSent(string $mailable): void
    {
        $matching = array_filter($this->sent, fn($m) => $m['mailable'] === $mailable);
        if (!empty($matching)) {
            throw new \Exception("Mail [{$mailable}] was unexpectedly sent.");
        }
    }

    public function assertSentTo(string $recipient, string $mailable): void
    {
        $matching = array_filter(
            $this->sent,
            fn($m) => $m['mailable'] === $mailable && ($m['to'] ?? '') === $recipient
        );
        if (empty($matching)) {
            throw new \Exception("Mail [{$mailable}] was not sent to [{$recipient}].");
        }
    }

    public function assertSentTimes(string $mailable, int $times): void
    {
        $count = count(array_filter($this->sent, fn($m) => $m['mailable'] === $mailable));
        if ($count !== $times) {
            throw new \Exception("Mail [{$mailable}] sent {$count} time(s), expected {$times}.");
        }
    }

    public function assertNothingSent(): void
    {
        if (!empty($this->sent)) {
            throw new \Exception("Mails were unexpectedly sent.");
        }
    }

    // -------------------------------------------------------------------------
    // Inspection
    // -------------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    public function sent(string $mailable): array
    {
        return array_values(array_filter($this->sent, fn($m) => $m['mailable'] === $mailable));
    }

    public function hasSent(string $mailable): bool
    {
        return !empty(array_filter($this->sent, fn($m) => $m['mailable'] === $mailable));
    }

    public function flush(): void
    {
        $this->sent = [];
    }
}
