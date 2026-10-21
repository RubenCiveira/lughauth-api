<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway;

/**
 * Port the signer uses as a fallback when the scheduled key rotation has fallen behind.
 *
 * The scheduler is the main way keys are provisioned, so it is not on the request path. When it
 * has not run (no cron configured, or it failed), signing still works: the missing active key is
 * created on the spot and the rest of the chain is completed after the response is sent.
 */
interface SigningKeyProvisioner
{
    /**
     * Creates the tenant's active key right away when it has none, so the current token can be
     * signed, and schedules the future keys for after the response.
     *
     * @throws \RuntimeException when the key pair cannot be generated.
     */
    public function provisionActiveKey(string $tenant): void;

    /**
     * Schedules, for after the response, the future keys the tenant is missing. Does nothing
     * when its chain already covers the configured future keys.
     */
    public function ensureFutureKeys(string $tenant): void;
}
