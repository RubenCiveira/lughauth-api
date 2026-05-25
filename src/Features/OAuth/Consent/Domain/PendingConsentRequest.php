<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Consent\Domain;

final class PendingConsentRequest
{
    public function __construct(
        public readonly string $userId,
        public readonly string $tenant,
        public readonly string $clientId,
        public readonly string $redirectUri,
        public readonly string $scope,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        string $userId,
        string $tenant,
        string $clientId,
        string $redirectUri,
        string $scope,
    ): self {
        return new self(
            userId: $userId,
            tenant: $tenant,
            clientId: $clientId,
            redirectUri: $redirectUri,
            scope: $scope,
            createdAt: new \DateTimeImmutable(),
        );
    }

    public function scopeList(): array
    {
        return array_values(array_filter(explode(' ', $this->scope)));
    }
}
