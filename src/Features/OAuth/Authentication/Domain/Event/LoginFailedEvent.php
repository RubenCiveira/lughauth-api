<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Event;

final class LoginFailedEvent extends OidcEvent
{
    public function __construct(
        string $tenant,
        public readonly string $username,
        public readonly string $reason,
        \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
        parent::__construct($tenant, $occurredAt);
    }
}
