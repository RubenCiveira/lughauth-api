<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain;

/**
 * Represents a successfully authenticated user within a live OAuth session.
 *
 * Resolved from an existing session cookie or state parameter at the start of
 * each authorization request. Carries the minimal set of identity attributes needed
 * by the authorization flow: the server-side session key, the subject identifier,
 * the client the session was created for, the issuer URL, the cross-session identifier
 * used to correlate tabs, and whether MFA was completed during login.
 *
 * An empty `userId` indicates no active identity;
 * use `isAuthenticated()` rather than checking the field directly.
 */
final class ActiveUser
{
    /**
     * Server-generated opaque session identifier stored in the AUTH_SESSION_ID cookie.
     * Used to retrieve and invalidate the full session from the session store.
     */
    public readonly string $sessionId;

    /**
     * Subject identifier of the authenticated user (matches the `sub` claim in issued tokens).
     * An empty string signals an unauthenticated state; always prefer isAuthenticated() over direct comparison.
     */
    public readonly string $userId;

    /**
     * Identifier of the OAuth client for which this session was originally established.
     * Used to enforce that a session is only reused within the same client context.
     */
    public readonly string $clientId;

    /**
     * Base issuer URL for the tenant under which this session was created.
     * Included in issued ID tokens and used for issuer validation during session resumption.
     */
    public readonly string $issuer;

    /**
     * Cross-session identifier that links multiple browser tabs or parallel flows to the same user identity.
     * Allows an existing session to be reused without forcing the user to re-authenticate in a new tab.
     */
    public readonly string $csid;

    /**
     * Indicates whether the session was established after a successful multi-factor authentication step.
     * Downstream services may require this flag to be true before granting access to sensitive resources.
     */
    public readonly bool $withMfa;

    public function __construct(
        string $sessionId,
        string $userId,
        string $clientId,
        string $issuer,
        string $csid,
        bool $withMfa,
    ) {
        $this->sessionId = $sessionId;
        $this->userId = $userId;
        $this->clientId = $clientId;
        $this->issuer = $issuer;
        $this->csid = $csid;
        $this->withMfa = $withMfa;
    }

    /**
     * Returns true when this instance represents a fully authenticated user with a non-empty subject.
     * Use this method instead of comparing userId directly to avoid coupling to the empty-string sentinel.
     */
    public function isAuthenticated(): bool
    {
        return $this->userId !== '';
    }
}
