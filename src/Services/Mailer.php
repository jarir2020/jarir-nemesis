<?php
declare(strict_types=1);
namespace Nemesis\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Nemesis\Core\Config;

class Mailer {
    protected $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->setup();
    }

    protected function setup() {
        $this->mail->isSMTP();
        $this->mail->Host       = Config::get('MAIL_HOST', 'smtp.gmail.com');
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = Config::get('MAIL_USER');
        $this->mail->Password   = Config::get('MAIL_PASS');
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = Config::get('MAIL_PORT', 587);

        $this->mail->setFrom(Config::get('MAIL_FROM'), Config::get('MAIL_FROM_NAME', 'Nemesis Mailer'));
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
