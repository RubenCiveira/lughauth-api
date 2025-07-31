<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\Notification\Message\Infrastructure\Driven;

use Exception;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\PHPMailer;
use Civi\Lughauth\Shared\AppConfig;
use Civi\Lughauth\Features\Notification\Message\Domain\Gateway\MailSenderRepository;
use Civi\Lughauth\Features\Notification\Message\Domain\Message;

class MailSenderAdapter implements MailSenderRepository
{
    private readonly string $host;
    private readonly int $port;
    private readonly string $senderName;
    private readonly string $senderEmail;
    private readonly string $username;
    private readonly string $password;
    private readonly bool $tls;
    private readonly string $auth;

    public function __construct(AppConfig $conf)
    {
        $this->host = $conf->get('mailer.host', 'localhost');
        $this->port = (int)$conf->get('mailer.port', '587');
        $this->username = $conf->get('mailer.username', '');
        $this->password = $conf->get('mailer.password', '');
        $this->tls = $conf->get('mailer.start.tls', 'none') == 'REQUIRED';
        $this->auth = $conf->get('mailer.auth.methods', '');
        $this->senderName = $conf->get('mailer.from.name', 'info');
        $this->senderEmail = $conf->get('mailer.from.email', 'info@localhost');
    }
    public function sendMessage(Message $message)
    {
        $mail = new PHPMailer();
        $mail->isSMTP();
        $mail->SMTPDebug = SMTP::DEBUG_CONNECTION;
        $mail->Host = $this->host;
        $mail->Port = $this->port;
        if ($this->tls) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        if ($this->auth) {
            $mail->SMTPAuth = true;
        }
        $mail->Username = $this->username;
        $mail->Password = $this->password;
        $mail->setFrom($this->senderEmail, $this->senderName);
        $mail->Subject = $message->subject;
        if ($message->htmlContent) {
            $mail->isHTML(true);
            $mail->Body = $message->htmlContent;
            $mail->AltBody = $message->txtContent;
        } else {
            $mail->isHTML(false);
            $mail->Body = $message->txtContent;
        }
        $mail->AddAddress($message->targetAddress, $message->targetName);
        ob_start();
        $result = $mail->send();
        $trace = ob_get_clean();
        ob_end_clean();
        if (!$result) {
            throw new Exception($mail->ErrorInfo . ": " . $trace);
        }
    }
}
