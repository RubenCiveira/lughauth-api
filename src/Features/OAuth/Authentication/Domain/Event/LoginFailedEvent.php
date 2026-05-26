<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Event;

/**
 * Domain event raised when a login attempt fails for any reason.
 *
 * Published after the authentication service determines that the supplied credentials
 * are invalid, the account does not exist, or access is otherwise denied. Listeners can
 * use this event to implement brute-force protection, security audit logging, or alerting
 * workflows. The reason field carries the AuthenticationResult error constant so that
 * subscribers can distinguish between different failure modes without re-inspecting the
 * original request.
 */
final class LoginFailedEvent extends OidcEvent
{
    /**
     * Username or identifier that was used in the failed login attempt.
     * May be an email address, a subject ID, or any other identifier accepted by the tenant.
     */
    public readonly string $username;

    /**
     * Error constant from AuthenticationResult describing why the login failed.
     * Corresponds to one of the ERR_* constants, e.g., ERR_WRONG_CREDENTIAL or ERR_UNKNOW_USER.
     */
    public readonly string $reason;

    public function __construct(
        string $tenant,
        string $username,
        string $reason,
        \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
        $this->username = $username;
        $this->reason = $reason;
        parent::__construct($tenant, $occurredAt);
    }
}
