<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain;

final class ActiveUser
{
    public function __construct(
        public readonly string $sessionId,
        public readonly string $userId,
        public readonly string $clientId,
        public readonly string $issuer,
        public readonly string $csid,
        public readonly bool $withMfa,
    ) {
    }

    public function isAuthenticated(): bool
    {
        return $this->userId !== '';
    }
}
