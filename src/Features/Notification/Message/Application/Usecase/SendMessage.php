<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\Notification\Message\Application\Usecase;

use Civi\Lughauth\Features\Notification\Message\Domain\Gateway\MailSenderGateway;
use Civi\Lughauth\Features\Notification\Message\Domain\Message;
use Civi\Lughauth\Features\Notification\Message\Domain\SenderListener;
use Civi\Lughauth\Shared\Observability\LoggerAwareTrait;
use Exception;

class SendMessage
{
    use LoggerAwareTrait;

    public function __construct(private readonly MailSenderGateway $gateway)
    {

    }
    public function send(Message $message, ?SenderListener $listener = null)
    {
        $gateway = $this->gateway;
        register_shutdown_function(function () use ($message, $listener, $gateway) {
            $send = false;
            try {
                $gateway->sendMessage($message);
                $send = true;
            } catch (Exception $ex) {
                if ($listener) {
                    $listener->ko($ex);
                }
                $this->logError('Unable to send email: ' . $ex->getMessage());
            }
            if ($send && $listener) {
                $listener->ok();
            }
        });
    }
}
