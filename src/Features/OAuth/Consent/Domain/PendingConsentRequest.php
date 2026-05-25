<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Consent\Domain;

/**
 * A queued consent item awaiting user action in the authorization flow.
 *
 * Created when the consent orchestrator detects that the user has not yet
 * approved the requested scopes for a given client. `scopeList()` returns
 * the individual scope strings so the UI can render per-scope consent items.
 */
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
