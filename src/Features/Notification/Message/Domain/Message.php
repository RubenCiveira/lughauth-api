<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\Notification\Message\Domain;

class Message
{
    public function __construct(
        public readonly string $targetName,
        public readonly string $targetAddress,
        public readonly string $subject,
        public readonly string $txtContent,
        public readonly string $htmlContent,
        public readonly array $attacheds = []
    ) {
    }
}
