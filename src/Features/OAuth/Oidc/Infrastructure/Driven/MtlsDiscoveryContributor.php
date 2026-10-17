<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Oidc\Infrastructure\Driven;

use Override;
use Civi\Lughauth\Features\OAuth\Oidc\Domain\MtlsEndpointAliases;
use Civi\Lughauth\Features\OAuth\Oidc\Domain\OpenIdConfiguration;
use Civi\Lughauth\Features\OAuth\Oidc\Domain\Gateway\DiscoveryContributorGateway;

/**
 * DiscoveryContributorGateway implementation that injects RFC 8705 mTLS endpoint aliases.
 *
 * Aliases are only published when a trusted mTLS-terminating base URL is configured
 * (app.oauth.mtls-base-url, e.g. an nginx instance that terminates client-certificate TLS);
 * the aliases share the standard path structure but use that host. Without it nothing is
 * contributed, because advertising aliases on the regular host would claim mTLS support
 * that the deployment does not provide.
 */
final class MtlsDiscoveryContributor implements DiscoveryContributorGateway
{
    public function __construct(
        /** Optional mTLS-terminating base URL; when null or empty no aliases are published. */
        private readonly ?string $mtlsBaseUrl = null,
    ) {
    }

    /**
     * Returns the mtls_endpoint_aliases map derived from the mTLS base URL, or nothing when it is not configured.
     */
    #[Override]
    public function contribute(OpenIdConfiguration $base, string $tenant): array
    {
        if ($this->mtlsBaseUrl === null || $this->mtlsBaseUrl === '') {
            return [];
        }

        $aliases = MtlsEndpointAliases::forBase(rtrim($this->mtlsBaseUrl, '/') . "/openid/$tenant");
        return ['mtls_endpoint_aliases' => $aliases->toArray()];
    }
}
