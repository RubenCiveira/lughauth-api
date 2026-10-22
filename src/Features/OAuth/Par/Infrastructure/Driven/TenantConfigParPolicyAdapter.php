<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Par\Infrastructure\Driven;

use Override;
use Civi\Lughauth\Features\Access\Tenant\Domain\Gateway\TenantQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\TenantConfig\Domain\Gateway\TenantConfigQueryRepositoryGateway;
use Civi\Lughauth\Features\OAuth\Par\Domain\Gateway\TenantParPolicyGateway;

/**
 * Reads the tenant-wide PAR policy from `tenant-config.require-pushed-authorization-requests`.
 * A tenant without configuration does not require PAR.
 */
class TenantConfigParPolicyAdapter implements TenantParPolicyGateway
{
    public function __construct(
        private readonly TenantQueryRepositoryGateway $tenants,
        private readonly TenantConfigQueryRepositoryGateway $configs,
    ) {
    }

    #[Override]
    public function requiresPar(string $tenant): bool
    {
        $found = $this->tenants->findOneByName($tenant);
        $config = $found !== null ? $this->configs->findOneByTenant($found) : null;
        return $config?->isRequirePushedAuthorizationRequests() ?? false;
    }
}
