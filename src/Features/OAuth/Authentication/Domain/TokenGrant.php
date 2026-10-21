<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject\ScopeSet;
use Civi\Lughauth\Features\OAuth\Client\Domain\ClientData;
use Civi\Lughauth\Features\OAuth\Session\Domain\SessionInfo;

/**
 * What a grant or an authorization response authorised, ready to be turned into tokens.
 *
 * Built by the protocol drivers (token endpoint, authorization endpoint) once they have
 * authenticated the client and resolved the grant; TokenFactory and ClaimsMapper derive every
 * token and claim from it, so all flows share the same rules per token type.
 */
final class TokenGrant
{
    /**
     * @param string                          $tenant      Tenant name, which owns the signing keys
     * @param string                          $grantType   Grant (or "implicit" for the front channel)
     * @param string[]                        $audiences   Resource servers granted besides the client
     * @param SessionInfo|null                $session     Browser session the grant comes from, if any
     * @param string|null                     $nonce       Nonce of the authorization request (code and front channel only)
     * @param array{fid: string, fexp: int}|null $family   Refresh family a rotated refresh token keeps
     * @param string|null                     $originGrant Grant that opened the refresh family
     * @param ScopeSet|null                   $refreshScope Scope a rotated refresh token keeps when
     *                                                      the request narrowed $scope (RFC 6749 §6)
     */
    public function __construct(
        public readonly string $tenant,
        public readonly string $issuer,
        public readonly ClientData $client,
        public readonly string $grantType,
        public readonly AuthenticationResult $auth,
        public readonly ScopeSet $scope,
        public readonly array $audiences = [],
        public readonly ?SessionInfo $session = null,
        public readonly ?string $nonce = null,
        public readonly ?array $family = null,
        public readonly ?string $originGrant = null,
        public readonly ?ScopeSet $refreshScope = null,
    ) {
    }

    public function isClientCredentials(): bool
    {
        return $this->grantType === 'client_credentials';
    }
}
