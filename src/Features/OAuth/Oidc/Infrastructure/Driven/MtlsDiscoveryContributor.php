<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Oidc\Infrastructure\Driven;

use Override;
use Civi\Lughauth\Features\OAuth\Oidc\Domain\OpenIdConfiguration;
use Civi\Lughauth\Features\OAuth\Oidc\Domain\Gateway\DiscoveryContributorGateway;

/**
 * Contributes RFC 8705 mTLS endpoint aliases to the discovery document.
 * Override mtls_base_url in config to point to the mTLS-terminating reverse proxy.
 */
final class MtlsDiscoveryContributor implements DiscoveryContributorGateway
{
    public function __construct(
        private readonly ?string $mtlsBaseUrl = null,
    ) {
    }

    #[Override]
    public function contribute(OpenIdConfiguration $base, string $tenant): array
    {
        $effectiveBase = $this->mtlsBaseUrl !== null && $this->mtlsBaseUrl !== ''
            ? rtrim($this->mtlsBaseUrl, '/') . "/openid/$tenant"
            : null;

        if ($effectiveBase === null) {
            return ['mtls_endpoint_aliases' => $base->mtlsEndpointAliases->toArray()];
        }

        return [
            'mtls_endpoint_aliases' => [
                'token_endpoint' => "$effectiveBase/token",
                'revocation_endpoint' => "$effectiveBase/revoke",
                'introspection_endpoint' => "$effectiveBase/introspect",
                'device_authorization_endpoint' => "$effectiveBase/device",
                'registration_endpoint' => "$effectiveBase/register",
                'userinfo_endpoint' => "$effectiveBase/userinfo",
                'pushed_authorization_request_endpoint' => "$effectiveBase/par",
                'backchannel_authentication_endpoint' => "$effectiveBase/backchannel",
            ],
        ];
    }
}
