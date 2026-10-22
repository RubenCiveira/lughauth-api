<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Gateway;

/**
 * Port for how long an authentication serves other authorizations through SSO
 * (`tenant-config.session-sso-ttl-seconds`).
 */
interface SessionSsoTtlGateway
{
    /** Seconds since auth_time during which the tenant (by name) reuses the session. */
    public function forTenant(string $tenantName): int;
}
