<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain;

/**
 * Represents a successfully authenticated user within a live OAuth session.
 *
 * Resolved from an existing session cookie or state parameter at the start of
 * each authorization request. An empty `userId` indicates no active identity;
 * use `isAuthenticated()` rather than checking the field directly.
 */
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
