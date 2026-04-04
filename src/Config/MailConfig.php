<?php
declare(strict_types=1);

// Nemesis 4.0.0 | Phase 9 — Typed Config DTOs | Updated: 2026-04-03

namespace Nemesis\Config;

readonly class MailConfig
{
    public function __construct(
        public string $mailer,
        public string $host,
        public int    $port,
        public string $username,
        public string $password,
        public string $encryption,
        public string $fromAddress,
        public string $fromName,
    ) {}

    public static function fromEnv(): static
    {
        return new static(
            mailer:      (string) (getenv('MAIL_MAILER')     ?: 'smtp'),
            host:        (string) (getenv('MAIL_HOST')       ?: 'smtp.mailtrap.io'),
            port:        (int)    (getenv('MAIL_PORT')       ?: 587),
            username:    (string) (getenv('MAIL_USER')       ?: ''),
            password:    (string) (getenv('MAIL_PASS')       ?: ''),
            encryption:  (string) (getenv('MAIL_ENCRYPTION') ?: 'tls'),
            fromAddress: (string) (getenv('MAIL_FROM')       ?: 'hello@example.com'),
            fromName:    (string) (getenv('MAIL_FROM_NAME')  ?: 'Nemesis'),
        );
    }

    public static function required(): array
    {
        return ['MAIL_HOST', 'MAIL_FROM'];
    }
}
