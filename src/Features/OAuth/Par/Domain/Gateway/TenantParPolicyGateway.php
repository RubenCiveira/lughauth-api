<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Par\Domain\Gateway;

/**
 * Port for the tenant-wide PAR policy (`tenant-config.require-pushed-authorization-requests`).
 *
 * When a tenant requires PAR, every authorization request of every client must be pushed
 * first, and discovery announces `require_pushed_authorization_requests`. A single client can
 * also require it on its own (ClientData::requirePar) without affecting discovery.
 */
interface TenantParPolicyGateway
{
    /**
     * True when the tenant, by name, requires every authorization request to go through PAR.
     */
    public function requiresPar(string $tenant): bool;
}
