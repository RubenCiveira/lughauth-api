<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\TokenSecurity\Application;

use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\KeyConfig;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\KeyPairGenerator;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\TokenStoreGateway;
use Civi\Lughauth\Shared\Observability\LoggerAwareTrait;

/**
 * Provisions and rotates each tenant's signing keys outside of any token request.
 *
 * Keys are chained without overlap: each one starts when the previous one expires, so exactly
 * one key is active at any time. Every run tops the chain up until it covers the active key plus
 * `futures` keys, which are published in the JWKS before they start signing; retired keys stay
 * published for `retention` and are purged afterwards. Whenever a tenant's keys change, only
 * that tenant's cached JWKS is invalidated.
 *
 * `rotateAll` runs from the scheduler; `rotate` is also called when a tenant is created so it
 * can sign tokens straight away.
 */
final class KeyRotationService
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly KeyConfig $config,
        private readonly TokenStoreGateway $keys,
        private readonly KeyPairGenerator $generator,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Cache entry holding the tenant's serialized JWKS (hashed: PSR-16 keys forbid some characters).
     */
    public static function jwksCacheKey(string $tenant): string
    {
        return 'jwks_public_keyset.' . md5($tenant);
    }

    /**
     * Rotates the keys of every enabled tenant, then purges keys past their retention.
     * A failing tenant is logged and does not stop the others.
     */
    public function rotateAll(): void
    {
        foreach ($this->keys->tenantsToProvision() as $tenant) {
            try {
                $this->rotate($tenant);
            } catch (\Throwable $ex) {
                $this->logError('Signing key rotation failed for tenant ' . $tenant . ': ' . $ex->getMessage());
            }
        }
        $this->keys->purgeExpired((new DateTimeImmutable())->sub($this->config->retention));
    }

    /**
     * Generates the keys the tenant is missing to cover the active key plus the future ones.
     * Keys are only added once the chain covers less than `futures` key lifetimes ahead, so
     * frequent runs do not add a key each time the clock moves. Returns how many were created.
     */
    public function rotate(string $tenant): int
    {
        $now = new DateTimeImmutable();
        $minimum = $now;
        for ($i = 0; $i < $this->config->futures; $i++) {
            $minimum = $minimum->add($this->config->ttl);
        }
        $start = $this->keys->latestExpiration($tenant);
        if ($start !== null && $start >= $minimum) {
            return 0;
        }
        if ($start === null || $start < $now) {
            $start = $now;
        }
        $horizon = $minimum->add($this->config->ttl);
        $created = 0;
        while ($start < $horizon) {
            $this->keys->saveKey($tenant, $this->generator->generate(), $start, $this->config->ttl);
            $start = $start->add($this->config->ttl);
            $created++;
        }
        if ($created > 0) {
            $this->cache->delete(self::jwksCacheKey($tenant));
            $this->logInfo('Provisioned ' . $created . ' signing keys for tenant ' . $tenant);
        }
        return $created;
    }
}
