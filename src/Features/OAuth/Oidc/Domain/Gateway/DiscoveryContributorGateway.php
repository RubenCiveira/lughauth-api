<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Oidc\Domain\Gateway;

use Civi\Lughauth\Features\OAuth\Oidc\Domain\OpenIdConfiguration;

interface DiscoveryContributorGateway
{
    public function contribute(OpenIdConfiguration $base, string $tenant): array;
}
