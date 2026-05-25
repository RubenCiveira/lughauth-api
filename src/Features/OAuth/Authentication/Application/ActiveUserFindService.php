<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Application;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\ActiveUser;
use Civi\Lughauth\Features\OAuth\Session\Domain\Gateway\SessionStoreGateway;

final class ActiveUserFindService
{
    public function __construct(
        private readonly SessionStoreGateway $sessionStore,
    ) {
    }

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

    public function findByCsid(string $csid): ?ActiveUser
    {
        $sessionId = $this->sessionStore->findActiveSessionIdByCsid($csid);
        if ($sessionId === null) {
            return null;
        }

        return $this->findBySessionId($sessionId);
    }
}
