<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Event;

/**
 * Domain event raised when a user successfully completes the authentication flow.
 *
 * Published once a valid session has been persisted and an authorization response has been
 * prepared. Listeners may use this event to update last-login timestamps, emit audit log
 * entries, trigger welcome notifications, or synchronise session state to external systems.
 * The grantType field allows subscribers to distinguish between interactive browser logins
 * and programmatic flows such as the password or device grant.
 */
final class LoginSucceededEvent extends OidcEvent
{
    /**
     * Subject identifier of the user who successfully authenticated.
     * Matches the `sub` claim that will appear in the issued tokens for this session.
     */
    public readonly string $userId;

    /**
     * OAuth client identifier that initiated the authorization request.
     * Can be used by listeners to apply client-specific post-login logic.
     */
    public readonly string $clientId;

    /**
     * Server-side session identifier created during this successful login.
     * Used to correlate future events such as token refresh or logout with this session.
     */
    public readonly string $sessionId;

    /**
     * OAuth grant type that was used to authenticate (e.g., "authorization_code", "password").
     * Allows listeners to apply different auditing or rate-limiting rules per grant type.
     */
    public readonly string $grantType;

    public function __construct(
        string $tenant,
        string $userId,
        string $clientId,
        string $sessionId,
        string $grantType,
        \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
        $this->userId = $userId;
        $this->clientId = $clientId;
        $this->sessionId = $sessionId;
        $this->grantType = $grantType;
        parent::__construct($tenant, $occurredAt);
    }
}
