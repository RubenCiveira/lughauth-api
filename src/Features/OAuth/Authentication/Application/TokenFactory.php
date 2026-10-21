<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Application;

use DateInterval;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception\OAuthTokenException;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\Gateway\TokenLifetimesGateway;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\TokenGrant;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\TokenLifetimes;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\TokenSigner;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\TokenType;

/**
 * Issues the access, ID and refresh tokens of a grant with the claims of ClaimsMapper, the
 * purpose-specific signatures of TokenSigner and the tenant's TokenLifetimes.
 *
 * - Token endpoint: an access token always; an ID token for every grant but client_credentials;
 *   a refresh token only for a client registered for the refresh_token grant and, for an
 *   authorization code, only when it came from a browser session, so logout reaches every
 *   refresh token. A rotated refresh token stays in its family and never outlives the family
 *   deadline (fexp).
 * - Authorization endpoint (front channel): the access and ID tokens the response_type asks for,
 *   the ID token carrying at_hash, c_hash and s_hash for what travels with it.
 */
final class TokenFactory
{
    public function __construct(
        private readonly TokenSigner $signer,
        private readonly ClaimsMapper $claims,
        private readonly TokenLifetimesGateway $lifetimes,
    ) {
    }

    /**
     * Builds the token endpoint response body (RFC 6749 §5.1).
     *
     * @return array<string, int|string>
     */
    public function tokenResponse(TokenGrant $grant): array
    {
        $lifetimes = $this->lifetimes->forTenant($grant->tenant);
        $accessTtl = $grant->isClientCredentials()
            ? new DateInterval('PT' . max(1, $grant->client->m2mTokenTtlSeconds) . 'S')
            : $lifetimes->access;
        $accessToken = $this->signer->sign(TokenType::Access, $this->claims->access($grant), $grant->tenant, $accessTtl);
        $data = [
            'token_type' => 'Bearer',
            'expires_in' => self::seconds($accessTtl),
            'access_token' => $accessToken,
        ];
        if (!$grant->scope->isEmpty()) {
            $data['scope'] = $grant->scope->toString();
        }
        if (!$grant->isClientCredentials()) {
            $data['id_token'] = $this->signer->sign(TokenType::Id, $this->claims->id($grant, $accessToken), $grant->tenant, $lifetimes->id);
        }
        if (!$grant->isClientCredentials() && $grant->client->allowsGrant('refresh_token')
            && ($grant->grantType !== 'authorization_code' || $grant->session !== null)
        ) {
            $data['refresh_token'] = $this->refreshToken($grant, $lifetimes);
        }
        return $data;
    }

    /**
     * Builds the tokens returned in the authorization response itself (implicit and hybrid flows).
     *
     * @return array{access_token?: string, expires_in?: int, id_token?: string}
     */
    public function authorizationResponse(TokenGrant $grant, bool $withAccess, bool $withId, ?string $code, ?string $state): array
    {
        $lifetimes = $this->lifetimes->forTenant($grant->tenant);
        $data = [];
        $accessToken = null;
        if ($withAccess) {
            $accessToken = $this->signer->sign(TokenType::Access, $this->claims->access($grant), $grant->tenant, $lifetimes->access);
            $data['access_token'] = $accessToken;
            $data['expires_in'] = self::seconds($lifetimes->access);
        }
        if ($withId) {
            $data['id_token'] = $this->signer->sign(TokenType::Id, $this->claims->id($grant, $accessToken, $code, $state), $grant->tenant, $lifetimes->id);
        }
        return $data;
    }

    private function refreshToken(TokenGrant $grant, TokenLifetimes $lifetimes): string
    {
        $now = new DateTimeImmutable();
        $family = $grant->family ?? [
            'fid' => Uuid::uuid4()->toString(),
            'fexp' => $now->add($lifetimes->refreshFamily)->getTimestamp(),
        ];
        $expiresAt = min($now->add($lifetimes->refresh)->getTimestamp(), $family['fexp']);
        $ttl = $expiresAt - $now->getTimestamp();
        if ($ttl < 1) {
            throw OAuthTokenException::invalidGrant('Refresh token family expired');
        }
        return $this->signer->sign(
            TokenType::Refresh,
            $this->claims->refresh($grant, $family, $expiresAt),
            $grant->tenant,
            new DateInterval('PT' . $ttl . 'S')
        );
    }

    private static function seconds(DateInterval $interval): int
    {
        return (new DateTimeImmutable('@0'))->add($interval)->getTimestamp();
    }
}
