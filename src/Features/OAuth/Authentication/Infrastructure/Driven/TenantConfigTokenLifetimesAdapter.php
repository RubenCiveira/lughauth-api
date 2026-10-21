<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driven;

use Override;
use Civi\Lughauth\Features\Access\Tenant\Domain\Gateway\TenantQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\TenantConfig\Domain\Gateway\TenantConfigQueryRepositoryGateway;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\Gateway\TokenLifetimesGateway;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\TokenLifetimes;

/**
 * Reads the refresh token lifetimes from the tenant configuration
 * (`refresh-token-ttl-seconds` and `refresh-token-max-lifetime-seconds`).
 */
class TenantConfigTokenLifetimesAdapter implements TokenLifetimesGateway
{
    public function __construct(
        private readonly TenantQueryRepositoryGateway $tenants,
        private readonly TenantConfigQueryRepositoryGateway $configs,
    ) {
    }

    #[Override]
    public function forTenant(string $tenantName): TokenLifetimes
    {
        $tenant = $this->tenants->findOneByName($tenantName);
        $config = $tenant !== null ? $this->configs->findOneByTenant($tenant) : null;
        if ($config === null) {
            return new TokenLifetimes();
        }
        return new TokenLifetimes(
            refreshSeconds: $config->getRefreshTokenTtlSeconds(),
            refreshFamilySeconds: $config->getRefreshTokenMaxLifetimeSeconds(),
        );
    }
}
