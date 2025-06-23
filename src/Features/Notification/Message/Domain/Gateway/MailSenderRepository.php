<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\Notification\Message\Domain\Gateway;

use Civi\Lughauth\Features\Notification\Message\Domain\Message;

interface MailSenderRepository
{
    public function sendMessage(Message $message);
}
