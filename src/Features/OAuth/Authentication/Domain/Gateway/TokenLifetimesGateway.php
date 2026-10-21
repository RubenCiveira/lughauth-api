<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Gateway;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\TokenLifetimes;

/**
 * Port that resolves the token lifetimes configured for a tenant.
 */
interface TokenLifetimesGateway
{
    /** Lifetimes of the tenant (by name); the defaults when it has no configuration. */
    public function forTenant(string $tenantName): TokenLifetimes;
}
