<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\Notification\Message\Domain\Gateway;

use Psr\Container\ContainerInterface;
use Civi\Lughauth\Features\Notification\Message\Domain\Message;
use Civi\Lughauth\Features\Notification\Message\Infrastructure\Driven\MailSenderAdapter;

class MailSenderGateway
{
    private readonly MailSenderRepository $repository;

    public function __construct(ContainerInterface $container, ?MailSenderRepository $repository = null)
    {
        $this->repository = $repository ?? $container->get(MailSenderAdapter::class);
    }

    public function sendMessage(Message $message)
    {
        $this->repository->sendMessage($message);
    }

}
