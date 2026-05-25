<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Event;

final class UserLockedEvent extends OidcEvent
{
    public function __construct(
        string $tenant,
        public readonly string $userId,
        public readonly \DateTimeImmutable $lockedUntil,
        \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
        parent::__construct($tenant, $occurredAt);
    }
}
