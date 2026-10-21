<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\TokenSecurity\Application;

use Closure;
use Override;
use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Lock\LockFactory;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\KeyConfig;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\KeyPairGenerator;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\SigningKeyProvisioner;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\TokenStoreGateway;
use Civi\Lughauth\Shared\Observability\LoggerAwareTrait;

/**
 * Provisions and rotates each tenant's signing keys.
 *
 * Keys are chained without overlap: each one starts when the previous one expires, so exactly
 * one key is active at any time. Every run tops the chain up until it covers the active key plus
 * `futures` keys, which are published in the JWKS before they start signing; retired keys stay
 * published for `retention` and are purged afterwards. Whenever a tenant's keys change, only
 * that tenant's cached JWKS is invalidated.
 *
 * `rotateAll` runs from the scheduler and does the bulk of the work; `rotate` is also called when
 * a tenant is created so it can sign tokens straight away. As a fallback for when the scheduler
 * has not run, the signer calls `provisionActiveKey` (no active key: one is created on the spot)
 * and `ensureFutureKeys` (chain too short: completed after the response). A per-tenant lock keeps
 * the scheduler and concurrent requests from creating the same keys twice.
 */
final class KeyRotationService implements SigningKeyProvisioner
{
    use LoggerAwareTrait;

    /** Upper bound for a whole chain to be generated (RSA-4096 takes about a second per key). */
    private const int LOCK_TTL = 300;

    /** @var Closure(callable): void */
    private readonly Closure $defer;

    /** @var array<string, DateTimeImmutable> Chain end already seen to be long enough, per tenant. */
    private array $coveredUntil = [];

    /** @var array<string, true> Tenants whose rotation is already scheduled for after the response. */
    private array $deferred = [];

    /**
     * @param (Closure(callable): void)|null $defer Schedules the fallback rotation; defaults to running it at shutdown
     */
    public function __construct(
        private readonly KeyConfig $config,
        private readonly TokenStoreGateway $keys,
        private readonly KeyPairGenerator $generator,
        private readonly CacheInterface $cache,
        private readonly LockFactory $locks,
        ?Closure $defer = null,
    ) {
        $this->defer = $defer ?? static function (callable $job): void {
            register_shutdown_function($job);
        };
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
        return $this->locked($tenant, fn (): int => $this->provision($tenant, PHP_INT_MAX));
    }

    #[Override]
    public function provisionActiveKey(string $tenant): void
    {
        $this->logWarning('Tenant ' . $tenant . ' has no active signing key: creating it while signing. The scheduled key rotation should have provisioned it.');
        $this->locked($tenant, fn (): int => $this->keys->currentKey($tenant) === null ? $this->provision($tenant, 1) : 0);
        $this->deferRotation($tenant);
    }

    #[Override]
    public function ensureFutureKeys(string $tenant): void
    {
        $needed = $this->coverageNeeded(new DateTimeImmutable());
        if (isset($this->coveredUntil[$tenant]) && $this->coveredUntil[$tenant] >= $needed) {
            return;
        }
        $latest = $this->keys->latestExpiration($tenant);
        if ($latest !== null && $latest >= $needed) {
            $this->coveredUntil[$tenant] = $latest;
            return;
        }
        $this->deferRotation($tenant);
    }

    /**
     * Creates at most $max of the keys the tenant is missing, chained after its last one.
     * Must run under the tenant's lock.
     */
    private function provision(string $tenant, int $max): int
    {
        $now = new DateTimeImmutable();
        $needed = $this->coverageNeeded($now);
        $start = $this->keys->latestExpiration($tenant);
        if ($start !== null && $start >= $needed) {
            return 0;
        }
        if ($start === null || $start < $now) {
            $start = $now;
        }
        $created = 0;
        try {
            // Up to the key that runs past $needed: the active key plus `futures` keys. Comparing with
            // the chain's own end (not now + n·ttl) keeps clock drift from adding an extra key.
            while ($start <= $needed && $created < $max) {
                $this->keys->saveKey($tenant, $this->generator->generate(), $start, $this->config->ttl);
                $start = $start->add($this->config->ttl);
                $created++;
            }
        } finally {
            // Also when generation fails halfway: the keys already stored must be published.
            if ($created > 0) {
                $this->cache->delete(self::jwksCacheKey($tenant));
                $this->logInfo('Provisioned ' . $created . ' signing keys for tenant ' . $tenant);
            }
        }
        return $created;
    }

    /**
     * Instant the chain must reach so that `futures` keys remain after the active one.
     */
    private function coverageNeeded(DateTimeImmutable $now): DateTimeImmutable
    {
        $needed = $now;
        for ($i = 0; $i < $this->config->futures; $i++) {
            $needed = $needed->add($this->config->ttl);
        }
        return $needed;
    }

    private function deferRotation(string $tenant): void
    {
        if (isset($this->deferred[$tenant])) {
            return;
        }
        $this->deferred[$tenant] = true;
        ($this->defer)(function () use ($tenant): void {
            unset($this->deferred[$tenant]);
            try {
                $this->rotate($tenant);
            } catch (\Throwable $ex) {
                $this->logError('Deferred signing key rotation failed for tenant ' . $tenant . ': ' . $ex->getMessage());
            }
        });
    }

    /**
     * @param callable(): int $work
     */
    private function locked(string $tenant, callable $work): int
    {
        $lock = $this->locks->createLock('signing_keys.' . md5($tenant), self::LOCK_TTL);
        $lock->acquire(true);
        try {
            return $work();
        } finally {
            $lock->release();
        }
    }
}
