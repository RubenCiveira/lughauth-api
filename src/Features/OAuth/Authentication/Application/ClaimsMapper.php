<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Application;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\TokenGrant;
use Civi\Lughauth\Features\OAuth\Session\Domain\SessionInfo;

/**
 * Single place that decides the claims of each token type, shared by the token endpoint and the
 * authorization endpoint (and later UserInfo and the claim extensions).
 *
 * - Access token (RFC 9068 profile): iss, sub, aud (client and granted resource servers),
 *   client_id, scope, plus the tenant, roles and groups the resource servers authorise with, and
 *   the auth_time, amr and acr of the browser session, so a resource server can ask for a step-up
 *   (RFC 9470, task 067).
 *   iat, exp and jti are added by the signer, and the `at+jwt` header by its token type.
 * - ID token (OIDC Core §2): addressed only to the relying party (aud = azp = client). auth_time,
 *   amr, acr and sid come from the browser session and are left out when there is no evidence:
 *   auth_time is never the issuing instant, and the sid is the session's public identifier,
 *   never its cookie. The nonce only comes from the authorization request.
 * - Refresh token: the client, the family and the granted scope and audiences that later
 *   refreshes may narrow but never widen.
 *
 * Profile claims follow the exact granted scope values: `profile` gives name and `email` gives
 * email, so a scope such as `myprofile` gives neither.
 */
final class ClaimsMapper
{
    /** @return array<string, mixed> */
    public function access(TokenGrant $grant): array
    {
        $claims = [
            'iss' => $grant->issuer,
            'sub' => $grant->auth->id,
            'aud' => $this->audience($grant),
            'client_id' => $grant->client->id,
            'azp' => $grant->client->id,
            'scope' => $grant->scope->toString(),
            ...self::authenticationClaims($grant->session),
            ...$this->subjectClaims($grant),
        ];
        if ($grant->isClientCredentials()) {
            $claims['token_use'] = 'client_credentials';
        }
        return $claims;
    }

    /**
     * Claims of the ID token, with at_hash, c_hash and s_hash for the access token, code and state
     * returned with it.
     *
     * @return array<string, mixed>
     */
    public function id(TokenGrant $grant, ?string $accessToken = null, ?string $code = null, ?string $state = null): array
    {
        $session = $grant->session;
        return array_filter([
            'iss' => $grant->issuer,
            'sub' => $grant->auth->id,
            'aud' => $grant->client->id,
            'azp' => $grant->client->id,
            ...self::authenticationClaims($session),
            'sid' => $session !== null && $session->publicSid !== '' ? $session->publicSid : null,
            'nonce' => $grant->nonce !== '' ? $grant->nonce : null,
            'at_hash' => $accessToken !== null ? self::hash($accessToken) : null,
            'c_hash' => $code !== null ? self::hash($code) : null,
            's_hash' => $state !== null && $state !== '' ? self::hash($state) : null,
            ...$this->subjectClaims($grant),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Claims of a refresh token that expires at $expiresAt within the family $family.
     *
     * @param array{fid: string, fexp: int} $family
     * @return array<string, mixed>
     */
    public function refresh(TokenGrant $grant, array $family, int $expiresAt): array
    {
        $claims = [
            'keypass' => $grant->auth->id,
            'azp' => $grant->client->id,
            'iss' => $grant->issuer,
            'token_use' => 'refresh',
            'origin_grant' => $grant->originGrant ?? $grant->grantType,
            // Ceiling for later refreshes: they may narrow the scope and audiences but never widen them.
            'scope' => ($grant->refreshScope ?? $grant->scope)->toString(),
            'resources' => array_values(array_diff($grant->audiences, [$grant->client->id])),
            'fid' => $family['fid'],
            'fexp' => $family['fexp'],
            // Explicit exp: the signer would count the TTL from its own (later) clock reading.
            'exp' => $expiresAt,
        ];
        if ($grant->session !== null) {
            // Ties the refresh token to the browser session so logout stops further refreshes.
            $claims['sid'] = $grant->session->publicSid;
        }
        return $claims;
    }

    /**
     * Computes the OIDC half-hash of a value as required by the at_hash, c_hash and s_hash claims.
     * Uses SHA-256 (the RS256 hash), takes the left half of the digest, and encodes it as base64url.
     */
    public static function hash(string $value): string
    {
        $hashedValue = hash('sha256', $value, true);
        $halfHashedValue = substr($hashedValue, 0, intdiv(strlen($hashedValue), 2));
        return rtrim(strtr(base64_encode($halfHashedValue), '+/', '-_'), '=');
    }

    /**
     * auth_time, amr and acr of the browser session, only those it has evidence of: auth_time is
     * never the issuing instant.
     *
     * @return array<string, mixed>
     */
    private static function authenticationClaims(?SessionInfo $session): array
    {
        return array_filter([
            'auth_time' => $session?->authTime?->getTimestamp(),
            'amr' => $session !== null && $session->amr !== [] ? $session->amr : null,
            'acr' => $session?->acr,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** @return string|string[] the client alone as a string, or the client and resource servers */
    private function audience(TokenGrant $grant): string|array
    {
        $aud = array_values(array_unique([$grant->client->id, ...$grant->audiences]));
        return count($aud) === 1 ? $aud[0] : $aud;
    }

    /** @return array<string, mixed> */
    private function subjectClaims(TokenGrant $grant): array
    {
        $auth = $grant->auth;
        $claims = [
            'tenant' => $auth->tenant,
            'roles' => $auth->roles ?? [],
            'groups' => $auth->groups ?? [],
        ];
        if ($auth->tenancyMode === 'global') {
            $claims['tenancy'] = 'global';
        }
        if ($grant->scope->has('profile')) {
            $claims['name'] = $auth->name;
        }
        if ($grant->scope->has('email')) {
            $claims['email'] = $auth->email;
        }
        return $claims;
    }
}
