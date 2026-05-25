<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Application;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\ActiveUser;
use Civi\Lughauth\Features\OAuth\Session\Domain\Gateway\SessionStoreGateway;

/**
 * Resolves the currently authenticated user from an active OAuth session.
 *
 * This service acts as a read-only query layer over the session store. It translates
 * raw session data into an ActiveUser domain object so that other application services
 * can determine whether a valid, authenticated identity already exists for an incoming
 * authorization request without re-authenticating the user.
 *
 * Two lookup strategies are supported: by the server-side session identifier stored in
 * a cookie, and by the cross-session identifier (csid) that links browser tabs sharing
 * the same user identity across different OAuth flows.
 */
final class ActiveUserFindService
{
    public function __construct(
        private readonly SessionStoreGateway $sessionStore,
    ) {
    }

    /**
     * Looks up an active session by its server-generated session identifier.
     * Returns null when no matching session exists or when the session has expired.
     */
    public function findBySessionId(string $sessionId): ?ActiveUser
    {
        $session = $this->sessionStore->loadSession($sessionId);
        if ($session === null) {
            return null;
        }

        return new ActiveUser(
            sessionId: $sessionId,
            userId: $session->userId,
            clientId: $session->clientId,
            issuer: $session->issuer,
            csid: $session->csid,
            withMfa: $session->withMfa,
        );
    }

    /**
     * Looks up an active session using the cross-session identifier (csid).
     * This allows reusing an existing authenticated identity across different browser tabs or OAuth flows.
     */
    public function findByCsid(string $csid): ?ActiveUser
    {
        $sessionId = $this->sessionStore->findActiveSessionIdByCsid($csid);
        if ($sessionId === null) {
            return null;
        }

        return $this->findBySessionId($sessionId);
    }
}
