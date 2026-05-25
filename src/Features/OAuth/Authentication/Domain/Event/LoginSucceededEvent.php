<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Event;

final class LoginSucceededEvent extends OidcEvent
{
    public function __construct(
        string $tenant,
        public readonly string $userId,
        public readonly string $clientId,
        public readonly string $sessionId,
        public readonly string $grantType,
        \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
        parent::__construct($tenant, $occurredAt);
    }
}
