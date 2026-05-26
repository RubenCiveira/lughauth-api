<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Event;

/**
 * Domain event raised when a previously locked user account becomes accessible again.
 *
 * Published either when the automatic lock-expiry time is reached and the user attempts to
 * log in successfully, or when an administrator explicitly unlocks the account. Listeners can
 * use this event to clear any lock-related notifications sent to the user, to write an audit
 * entry, or to reset any downstream rate-limiting state that was set in response to the
 * corresponding UserLockedEvent.
 */
final class UserUnlockedEvent extends OidcEvent
{
    /**
     * Subject identifier of the user whose account has been unlocked.
     * Matches the `sub` claim used in tokens issued for this account.
     */
    public readonly string $userId;

    public function __construct(
        string $tenant,
        string $userId,
        \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
        $this->userId = $userId;
        parent::__construct($tenant, $occurredAt);
    }
}
