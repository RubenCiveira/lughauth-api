<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway;

use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\KeyPair;

/**
 * Port that generates new signing key pairs. Generation is expensive (RSA-4096), so it is only
 * invoked by the key rotation service, never while serving a request that signs or verifies.
 */
interface KeyPairGenerator
{
    /**
     * @throws \RuntimeException when the key pair cannot be generated.
     */
    public function generate(): KeyPair;
}
