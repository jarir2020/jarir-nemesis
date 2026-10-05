# Mailables

Nemesis mailables are message builders backed by the existing
`Nemesis\Services\Mailer`, PHPMailer configuration, and queue drivers.

```php
use Nemesis\Mail\Mailable;

final class WelcomeMail extends Mailable
{
    public function build(): static
    {
        return $this
            ->subject('Welcome')
            ->view('mail.welcome', ['name' => 'Alice'])
            ->text('mail.welcome-text');
    }
}
```

Send or queue it with the existing transport and queue infrastructure:

```php
$mailer = app(\Nemesis\Services\Mailer::class);
$mailer->to('alice@example.com')->send(new WelcomeMail());
$mailer->to('alice@example.com')->queue(new WelcomeMail(), 'default', 30);
```

`to`, `cc`, `bcc`, `from`, `replyTo`, `subject`, and attachment names reject
invalid email addresses and header line breaks. Attachments must be readable
files at delivery time. Views are rendered by Nemesis's existing `View` engine;
plain text is used as the alternative body when provided, otherwise it is
derived from the HTML body.

Tests can use `Mailer::fake()` and inspect `Mailer::sent()` or
`Mailer::assertSentTo()` without contacting SMTP. `Mailer::resetFake()` restores
real delivery after the test.

The legacy `$mailer->send($to, $subject, $html)` API remains supported.
