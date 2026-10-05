<?php
declare(strict_types=1);

namespace Nemesis\Services;

use InvalidArgumentException;
use Nemesis\Mail\Mailable;
use Nemesis\Mail\PendingMail;
use Nemesis\Queue\Queue;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Nemesis mail transport facade.
 *
 * The legacy send($to, $subject, $body) API remains supported. Mailable
 * messages use the same PHPMailer instance and can be queued through Nemesis
 * Queue; no second transport or queue implementation is introduced.
 */
class Mailer
{
    protected PHPMailer $mail;
    protected static bool $fake = false;
    /** @var list<array<string, mixed>> */
    protected static array $sent = [];

    public function __construct()
    {
        $this->mail = new PHPMailer(true);
        $this->setup();
    }

    protected function setup(): void
    {
        $host = function_exists('config')
            ? config('mail.mailers.smtp.host', getenv('MAIL_HOST') ?: 'smtp.mailgun.org')
            : (getenv('MAIL_HOST') ?: 'smtp.mailgun.org');
        $username = function_exists('config')
            ? config('mail.mailers.smtp.username', getenv('MAIL_USER') ?: '')
            : (getenv('MAIL_USER') ?: '');
        $password = function_exists('config')
            ? config('mail.mailers.smtp.password', getenv('MAIL_PASS') ?: '')
            : (getenv('MAIL_PASS') ?: '');
        $encryption = strtolower((string) (function_exists('config')
            ? config('mail.mailers.smtp.encryption', getenv('MAIL_ENCRYPTION') ?: 'tls')
            : (getenv('MAIL_ENCRYPTION') ?: 'tls')));
        $port = function_exists('config')
            ? config('mail.mailers.smtp.port', getenv('MAIL_PORT') ?: 587)
            : (getenv('MAIL_PORT') ?: 587);
        $from = function_exists('config')
            ? config('mail.from.address', getenv('MAIL_FROM') ?: $username)
            : (getenv('MAIL_FROM') ?: $username);
        $fromName = function_exists('config')
            ? config('mail.from.name', getenv('MAIL_FROM_NAME') ?: 'Nemesis Mailer')
            : (getenv('MAIL_FROM_NAME') ?: 'Nemesis Mailer');

        $this->mail->isSMTP();
        $this->mail->Host       = $host;
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = $username;
        $this->mail->Password   = $password;
        $this->mail->SMTPSecure = $encryption === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : ($encryption === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : '');
        $this->mail->Port = (int) $port;

        if (is_string($from) && $from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL) !== false) {
            $this->mail->setFrom($from, (string) $fromName);
        }
    }

    /**
     * Send either a legacy raw message or a composed Mailable.
     *
     * @param mixed $to Legacy recipient string or Mailable instance.
     */
    public function send($to, $subject = null, $body = null, $altBody = ''): bool
    {
        if ($to instanceof Mailable) {
            return $this->sendMailable($to);
        }

        if (!is_string($to) || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        if (!is_string($subject) || !is_string($body)) {
            return false;
        }
        if (strpbrk($subject, "\r\n\0") !== false) {
            throw new InvalidArgumentException('Mail subjects cannot contain line breaks.');
        }

        return $this->sendMessage([
            'to' => [$to => null],
            'cc' => [],
            'bcc' => [],
            'from' => null,
            'reply_to' => null,
            'subject' => $subject,
            'html' => $body,
            'text' => $altBody !== '' ? (string) $altBody : strip_tags($body),
            'attachments' => [],
            'mailable' => null,
        ]);
    }

    public function to(mixed $users): PendingMail
    {
        return (new PendingMail($this))->to($users);
    }

    public function queue(Mailable $mailable, string $queue = 'default', int $delay = 0): mixed
    {
        return $delay > 0
            ? Queue::later($delay, $mailable, $queue)
            : Queue::push($mailable, $queue);
    }

    /** Capture messages without contacting SMTP. */
    public static function fake(): void
    {
        self::$fake = true;
        self::$sent = [];
    }

    public static function real(): void
    {
        self::$fake = false;
    }

    /** @return list<array<string, mixed>> */
    public static function sent(): array
    {
        return self::$sent;
    }

    public static function resetFake(): void
    {
        self::$fake = false;
        self::$sent = [];
    }

    public static function assertSentTo(string $email, ?string $subject = null): bool
    {
        foreach (self::$sent as $message) {
            if (!array_key_exists($email, $message['to'] ?? [])) {
                continue;
            }
            if ($subject === null || ($message['subject'] ?? null) === $subject) {
                return true;
            }
        }
        return false;
    }

    public function getError(): string
    {
        return $this->mail->ErrorInfo;
    }

    protected function sendMailable(Mailable $mailable): bool
    {
        $mailable->build();
        $message = [
            'to' => $mailable->toAddresses,
            'cc' => $mailable->ccAddresses,
            'bcc' => $mailable->bccAddresses,
            'from' => $mailable->fromAddress,
            'reply_to' => $mailable->replyToAddress,
            'subject' => $mailable->subjectLine,
            'html' => $mailable->renderHtml(),
            'text' => $mailable->renderText(),
            'attachments' => $mailable->attachmentsList,
            'mailable' => $mailable::class,
        ];

        if ($message['to'] === []) {
            return false;
        }

        return $this->sendMessage($message);
    }

    /** @param array<string, mixed> $message */
    protected function sendMessage(array $message): bool
    {
        foreach ($message['attachments'] as $attachment) {
            if (!is_file($attachment['path']) || !is_readable($attachment['path'])) {
                throw new InvalidArgumentException('Mail attachment is not a readable file.');
            }
        }

        if (self::$fake) {
            self::$sent[] = $message;
            return true;
        }

        try {
            $this->mail->clearAllRecipients();
            $this->mail->clearAttachments();
            $this->mail->clearReplyTos();
            $this->mail->Subject = (string) $message['subject'];

            foreach ($message['to'] as $email => $name) {
                $this->mail->addAddress($email, $name ?: '');
            }
            foreach ($message['cc'] as $email => $name) {
                $this->mail->addCC($email, $name ?: '');
            }
            foreach ($message['bcc'] as $email => $name) {
                $this->mail->addBCC($email, $name ?: '');
            }
            if (is_array($message['from'])) {
                $this->mail->setFrom($message['from']['email'], $message['from']['name'] ?: '');
            }
            if (is_array($message['reply_to'])) {
                $this->mail->addReplyTo($message['reply_to']['email'], $message['reply_to']['name'] ?: '');
            }
            foreach ($message['attachments'] as $attachment) {
                $this->mail->addAttachment(
                    $attachment['path'],
                    $attachment['name'],
                    PHPMailer::ENCODING_BASE64,
                    $attachment['mime']
                );
            }

            $html = (string) $message['html'];
            $text = (string) $message['text'];
            if ($html !== '') {
                $this->mail->isHTML(true);
                $this->mail->Body = $html;
                $this->mail->AltBody = $text;
            } else {
                $this->mail->isHTML(false);
                $this->mail->Body = $text;
                $this->mail->AltBody = $text;
            }

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: {$this->mail->ErrorInfo}");
            return false;
        } finally {
            $this->mail->clearAllRecipients();
            $this->mail->clearAttachments();
            $this->mail->clearReplyTos();
        }
    }
}
