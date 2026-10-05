<?php
declare(strict_types=1);

namespace Nemesis\Mail;

use InvalidArgumentException;
use Nemesis\Core\View;
use Nemesis\Queue\Job;
use Nemesis\Services\Mailer;
use RuntimeException;

/**
 * Composable email message that can also be dispatched as a queue Job.
 *
 * Mailers remain responsible for transport and rendering; this class only
 * describes a message and validates data that can reach mail headers/files.
 */
abstract class Mailable extends Job
{
    /** @var array<string, string|null> */
    public array $toAddresses = [];
    /** @var array<string, string|null> */
    public array $ccAddresses = [];
    /** @var array<string, string|null> */
    public array $bccAddresses = [];
    /** @var array{email: string, name: string|null}|null */
    public ?array $fromAddress = null;
    /** @var array{email: string, name: string|null}|null */
    public ?array $replyToAddress = null;
    public string $subjectLine = '';
    public ?string $viewTemplate = null;
    public ?string $textViewTemplate = null;
    public ?string $rawHtml = null;
    public ?string $rawText = null;
    /** @var array<string, mixed> */
    public array $viewData = [];
    /** @var list<array{path: string, name: string, mime: string}> */
    public array $attachmentsList = [];

    public function build(): static
    {
        return $this;
    }

    public function from(string $address, ?string $name = null): static
    {
        $this->fromAddress = [
            'email' => self::validateAddress($address),
            'name' => self::validateHeader($name),
        ];
        return $this;
    }

    public function to(string|array $address, ?string $name = null): static
    {
        $this->mergeRecipients($this->toAddresses, $address, $name);
        return $this;
    }

    public function cc(string|array $address, ?string $name = null): static
    {
        $this->mergeRecipients($this->ccAddresses, $address, $name);
        return $this;
    }

    public function bcc(string|array $address, ?string $name = null): static
    {
        $this->mergeRecipients($this->bccAddresses, $address, $name);
        return $this;
    }

    public function replyTo(string $address, ?string $name = null): static
    {
        $this->replyToAddress = [
            'email' => self::validateAddress($address),
            'name' => self::validateHeader($name),
        ];
        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subjectLine = self::validateHeader($subject) ?? '';
        return $this;
    }

    /** @param array<string, mixed> $data */
    public function view(string $view, array $data = []): static
    {
        $view = trim($view);
        if ($view === '') {
            throw new InvalidArgumentException('A mail view name is required.');
        }
        $this->viewTemplate = $view;
        $this->viewData = array_merge($this->viewData, $data);
        return $this;
    }

    /** @param array<string, mixed> $data */
    public function textView(string $view, array $data = []): static
    {
        $view = trim($view);
        if ($view === '') {
            throw new InvalidArgumentException('A text mail view name is required.');
        }
        $this->textViewTemplate = $view;
        $this->viewData = array_merge($this->viewData, $data);
        return $this;
    }

    public function html(string $html): static
    {
        $this->rawHtml = $html;
        return $this;
    }

    public function text(string $text): static
    {
        $this->rawText = $text;
        return $this;
    }

    /** @param string|array<string, mixed> $key */
    public function with(string|array $key, mixed $value = null): static
    {
        if (is_array($key)) {
            $this->viewData = array_merge($this->viewData, $key);
        } else {
            $this->viewData[$key] = $value;
        }
        return $this;
    }

    /** @param array<string, string> $options */
    public function attach(string $file, array $options = []): static
    {
        $file = trim($file);
        if ($file === '') {
            throw new InvalidArgumentException('An attachment path is required.');
        }

        $name = (string) ($options['as'] ?? basename(str_replace('\\', '/', $file)));
        $name = basename(str_replace('\\', '/', $name));
        if ($name === '' || $name === '.' || $name === '..') {
            throw new InvalidArgumentException('Attachment name is invalid.');
        }

        $mime = (string) ($options['mime'] ?? 'application/octet-stream');
        self::validateHeader($name);
        self::validateHeader($mime);

        $this->attachmentsList[] = [
            'path' => $file,
            'name' => $name,
            'mime' => $mime,
        ];
        return $this;
    }

    public function renderHtml(): string
    {
        if ($this->rawHtml !== null) {
            return $this->rawHtml;
        }
        return $this->viewTemplate === null
            ? ''
            : View::make($this->viewTemplate, $this->viewData);
    }

    public function renderText(): string
    {
        if ($this->rawText !== null) {
            return $this->rawText;
        }
        if ($this->textViewTemplate !== null) {
            return View::make($this->textViewTemplate, $this->viewData);
        }
        return strip_tags($this->renderHtml());
    }

    public function send(?Mailer $mailer = null): bool
    {
        $mailer ??= $this->resolveMailer();
        return $mailer->send($this);
    }

    public function queue(?string $queue = 'default', int $delay = 0): mixed
    {
        $mailer = $this->resolveMailer();
        return $mailer->queue($this, $queue ?: 'default', $delay);
    }

    public function handle(): void
    {
        if (!$this->send()) {
            throw new RuntimeException('Mailable delivery failed.');
        }
    }

    public static function validateAddress(string $address): string
    {
        $address = trim($address);
        if (strpbrk($address, "\r\n\0") !== false || filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('A valid email address is required.');
        }
        return $address;
    }

    public static function validateHeader(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (strpbrk($value, "\r\n\0") !== false) {
            throw new InvalidArgumentException('Mail header values cannot contain line breaks.');
        }
        return $value;
    }

    /** @param array<string, string|null> $recipients */
    private function mergeRecipients(array &$recipients, string|array $address, ?string $name): void
    {
        if (is_string($address)) {
            $recipients[self::validateAddress($address)] = self::validateHeader($name);
            return;
        }

        foreach ($address as $key => $value) {
            if (is_int($key) || ctype_digit((string) $key)) {
                $email = self::validateAddress((string) $value);
                $recipientName = $name;
            } else {
                $email = self::validateAddress((string) $key);
                $recipientName = $value === null ? null : (string) $value;
            }
            $recipients[$email] = self::validateHeader($recipientName);
        }
    }

    private function resolveMailer(): Mailer
    {
        if (function_exists('app')) {
            return \app(Mailer::class);
        }
        return new Mailer();
    }
}
