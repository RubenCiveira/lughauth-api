<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\TokenSecurity\Application;

use Psr\SimpleCache\CacheInterface;
use Civi\Lughauth\Shared\Context;
use Civi\Lughauth\Shared\Exception\UnauthorizedException;
use Civi\Lughauth\Shared\Security\AccessTokenClaims;
use Civi\Lughauth\Features\Access\Tenant\Domain\Gateway\TenantQueryRepositoryGateway;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\TokenRevocationGateway;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\TokenSigner;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\TokenType;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\VerificationPolicy;

/**
 * Single verifier of bearer access tokens of this server, shared by UserInfo and introspection.
 * The JWT middleware never calls it: resource servers, this one included, reach it over HTTP
 * through the introspection endpoint (RFC 7662).
 *
 * A token is accepted only when:
 * 1. its signature, `typ: at+jwt` purpose and nbf/exp window are valid (TokenSigner);
 * 2. `iss` is the canonical issuer of the tenant that consumes it, never a configured fallback;
 * 3. `aud` is present and, when the consumer names audiences, contains at least one of them
 *    (RFC 7519 §4.1.3);
 * 4. the `tenant` claim is present and is that tenant (by name or uid), with no `main` default;
 * 5. its `jti` has not been revoked for that tenant.
 *
 * The window and audience checks are those of any resource server (AccessTokenClaims); steps 2,
 * 4 and 5 need this server's data. Only step 1 is cached, as the verified claims, for at most
 * AccessTokenClaims::MAX_CACHE_TTL seconds. Steps 2 to 5, and the expiry, are evaluated on every
 * call, so a revocation applies to the next request.
 */
final class AccessTokenPolicy
{
    private const string CACHE_PREFIX = 'access_token_claims_v1_';
    private const string TENANT_UID_PREFIX = 'access_token_tenant_uid_';

    public function __construct(
        private readonly TokenSigner $signer,
        private readonly TokenRevocationGateway $revocations,
        private readonly TenantQueryRepositoryGateway $tenants,
        private readonly Context $context,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Verifies an access token presented to an endpoint of the tenant and returns its claims.
     *
     * @param list<string> $audiences accepted audiences; empty accepts any audience
     * @return array<string, mixed>
     * @throws UnauthorizedException when the token is not an active access token of the tenant
     */
    public function verify(string $tenant, string $token, array $audiences = []): array
    {
        $claims = $this->verifiedClaims($tenant, $token);
        $this->check($claims, $tenant, $audiences);
        return $claims;
    }

    /**
     * Applies steps 2 to 5 to claims whose signature is already verified (for example against a
     * remote JWKS): issuer, audience, tenant, expiry and revocation.
     *
     * @param array<string, mixed> $claims
     * @param list<string> $audiences accepted audiences; empty accepts any audience
     * @throws UnauthorizedException
     */
    public function check(array $claims, string $tenant, array $audiences = []): void
    {
        AccessTokenClaims::assertActive($claims);
        if (($claims['iss'] ?? null) !== $this->context->getIssuer($tenant)) {
            throw new UnauthorizedException(message: 'The issuer is not valid.');
        }
        AccessTokenClaims::assertAudience($claims, $audiences);
        if (!$this->isTenant($claims['tenant'] ?? null, $tenant)) {
            throw new UnauthorizedException(message: 'The tenant is not valid.');
        }
        $jti = $claims['jti'] ?? null;
        if (!is_string($jti) || $jti === '' || $this->revocations->isRevoked($jti, $tenant)) {
            throw new UnauthorizedException(message: 'The provided JWT has been revoked.');
        }
    }

    /**
     * Resolves the tenant whose canonical issuer is `$issuer`, or null for a foreign issuer.
     */
    public function tenantOfIssuer(string $issuer): ?string
    {
        $prefix = $this->context->getIssuer('');
        if (!str_starts_with($issuer, $prefix)) {
            return null;
        }
        $tenant = substr($issuer, strlen($prefix));
        return $tenant !== '' && !str_contains($tenant, '/') ? $tenant : null;
    }

    /** @return array<string, mixed> */
    private function verifiedClaims(string $tenant, string $token): array
    {
        $key = self::CACHE_PREFIX . hash('sha256', $tenant . ' ' . $token);
        $cached = $this->cache->get($key);
        if (is_string($cached) && is_array($claims = json_decode($cached, true))) {
            return $claims;
        }
        $claims = $this->signer->verify($tenant, $token, VerificationPolicy::expecting(TokenType::Access));
        if ($claims === null) {
            throw new UnauthorizedException(message: 'The provided JWT could not be verified.');
        }
        $ttl = min(AccessTokenClaims::MAX_CACHE_TTL, (int) ($claims['exp'] ?? 0) - time());
        if ($ttl > 0) {
            $this->cache->set($key, json_encode($claims), $ttl);
        }
        return $claims;
    }

    /**
     * The claim holds the tenant uid for user tokens and its name for client credentials.
     */
    private function isTenant(mixed $claim, string $tenant): bool
    {
        if (!is_string($claim) || $claim === '') {
            return false;
        }
        return $claim === $tenant || $claim === $this->tenantUid($tenant);
    }

    private function tenantUid(string $tenant): ?string
    {
        $key = self::TENANT_UID_PREFIX . hash('sha256', $tenant);
        $cached = $this->cache->get($key);
        if (is_string($cached)) {
            return $cached;
        }
        $uid = $this->tenants->findOneByName($tenant)?->uid();
        if ($uid !== null) {
            $this->cache->set($key, $uid, AccessTokenClaims::MAX_CACHE_TTL);
        }
        return $uid;
    }
}
