<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Event;

/**
 * Domain event raised when a user account is locked following excessive failed login attempts.
 *
 * Published by the authentication service after the brute-force protection threshold is breached.
 * Listeners can use this event to send a security notification to the account owner, to write
 * an audit log entry, or to trigger an administrative alert. The lockedUntil timestamp indicates
 * when the lock expires automatically; a null value would indicate a permanent lock requiring
 * manual administrator intervention, though this implementation always supplies a concrete time.
 */
final class UserLockedEvent extends OidcEvent
{
    /**
     * Subject identifier of the user whose account has been locked.
     * Matches the `sub` claim used in tokens issued for this account.
     */
    public readonly string $userId;

    /**
     * Timestamp after which the account lock expires and login attempts are permitted again.
     * Listeners that send notifications to users should include this value in the message.
     */
    public readonly \DateTimeImmutable $lockedUntil;

    public function __construct(
        string $tenant,
        string $userId,
        \DateTimeImmutable $lockedUntil,
        \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
        $this->userId = $userId;
        $this->lockedUntil = $lockedUntil;
        parent::__construct($tenant, $occurredAt);
    }
}
