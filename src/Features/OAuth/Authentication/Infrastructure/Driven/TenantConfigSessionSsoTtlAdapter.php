<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driven;

use Override;
use Civi\Lughauth\Features\Access\Tenant\Domain\Gateway\TenantQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\TenantConfig\Domain\Gateway\TenantConfigQueryRepositoryGateway;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\Gateway\SessionSsoTtlGateway;

/**
 * Reads the SSO lifetime from `tenant-config.session-sso-ttl-seconds`. A tenant without
 * configuration, or with a non-positive value, gets the default of that configuration.
 */
class TenantConfigSessionSsoTtlAdapter implements SessionSsoTtlGateway
{
    /** Same default as `tenant-config.session-sso-ttl-seconds`. */
    public const int DEFAULT_SECONDS = 3600;

    public function __construct(
        private readonly TenantQueryRepositoryGateway $tenants,
        private readonly TenantConfigQueryRepositoryGateway $configs,
    ) {
    }

    #[Override]
    public function forTenant(string $tenantName): int
    {
        $tenant = $this->tenants->findOneByName($tenantName);
        $seconds = ($tenant !== null ? $this->configs->findOneByTenant($tenant) : null)?->getSessionSsoTtlSeconds();
        return $seconds !== null && $seconds > 0 ? $seconds : self::DEFAULT_SECONDS;
    }
}
