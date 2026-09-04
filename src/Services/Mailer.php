<?php
declare(strict_types=1);
namespace Nemesis\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    protected $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->setup();
    }

    protected function setup() {
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
        $this->mail->Port       = (int) $port;

        $this->mail->setFrom($from, $fromName);
    }

    public function send($to, $subject, $body, $altBody = '') {
        try {
            $this->mail->addAddress($to);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
            $this->mail->AltBody = $altBody ?: strip_tags($body);

            $this->mail->send();
            $this->mail->clearAddresses();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }

    public function getError() {
        return $this->mail->ErrorInfo;
    }
}
