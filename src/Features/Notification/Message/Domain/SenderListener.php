<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\Notification\Message\Domain;

use Exception;

interface SenderListener
{
    public function ok();

    public function ko(Exception $error);
}
