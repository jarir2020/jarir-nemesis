<?php
declare(strict_types=1);

namespace Nemesis\Mail;

use InvalidArgumentException;
use Nemesis\Services\Mailer;

/** Fluent recipient builder backed by the existing Nemesis Mailer. */
class PendingMail
{
    /** @var array<string, string|null> */
    protected array $toAddresses = [];
    /** @var array<string, string|null> */
    protected array $ccAddresses = [];
    /** @var array<string, string|null> */
    protected array $bccAddresses = [];

    public function __construct(protected Mailer $mailer)
    {
    }

    public function to(mixed $users, ?string $name = null): static
    {
        $this->addUsers($this->toAddresses, $users, $name);
        return $this;
    }

    public function cc(mixed $users, ?string $name = null): static
    {
        $this->addUsers($this->ccAddresses, $users, $name);
        return $this;
    }

    public function bcc(mixed $users, ?string $name = null): static
    {
        $this->addUsers($this->bccAddresses, $users, $name);
        return $this;
    }

    public function send(Mailable $mailable): bool
    {
        $this->applyRecipients($mailable);
        return $this->mailer->send($mailable);
    }

    public function queue(Mailable $mailable, string $queue = 'default', int $delay = 0): mixed
    {
        $this->applyRecipients($mailable);
        return $this->mailer->queue($mailable, $queue, $delay);
    }

    /** @param array<string, string|null> $recipients */
    private function addUsers(array &$recipients, mixed $users, ?string $name): void
    {
        if (is_object($users)) {
            if (!isset($users->email)) {
                throw new InvalidArgumentException('Recipient objects must expose an email property.');
            }
            $this->addAddress($recipients, (string) $users->email, $users->name ?? $name);
            return;
        }

        if (is_string($users)) {
            $this->addAddress($recipients, $users, $name);
            return;
        }

        if (!is_array($users)) {
            throw new InvalidArgumentException('Recipients must be an email, array, or object with email.');
        }

        foreach ($users as $key => $value) {
            if (is_int($key) || ctype_digit((string) $key)) {
                $this->addAddress($recipients, (string) $value, $name);
            } else {
                $this->addAddress($recipients, (string) $key, $value === null ? null : (string) $value);
            }
        }
    }

    /** @param array<string, string|null> $recipients */
    private function addAddress(array &$recipients, string $email, ?string $name): void
    {
        $recipients[Mailable::validateAddress($email)] = Mailable::validateHeader($name);
    }

    private function applyRecipients(Mailable $mailable): void
    {
        if ($this->toAddresses !== []) {
            $mailable->to($this->toAddresses);
        }
        if ($this->ccAddresses !== []) {
            $mailable->cc($this->ccAddresses);
        }
        if ($this->bccAddresses !== []) {
            $mailable->bcc($this->bccAddresses);
        }
    }
}
