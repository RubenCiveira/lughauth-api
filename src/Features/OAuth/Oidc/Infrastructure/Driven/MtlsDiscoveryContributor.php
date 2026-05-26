<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Oidc\Infrastructure\Driven;

use Override;
use Civi\Lughauth\Features\OAuth\Oidc\Domain\OpenIdConfiguration;
use Civi\Lughauth\Features\OAuth\Oidc\Domain\Gateway\DiscoveryContributorGateway;

/**
 * DiscoveryContributorGateway implementation that injects RFC 8705 mTLS endpoint aliases.
 *
 * When an mTLS base URL is configured (e.g. pointing at an nginx instance that terminates
 * client-certificate TLS), this contributor replaces the default mtls_endpoint_aliases in the
 * discovery document with URLs that share the same path structure but use the mTLS-terminating
 * host. When no mTLS base URL is configured it falls back to the aliases already present on the
 * base OpenIdConfiguration object so the field is always present in the discovery response.
 * Override mtls_base_url in the application configuration to enable mTLS-aware endpoint aliases.
 */
final class MtlsDiscoveryContributor implements DiscoveryContributorGateway
{
    public function __construct(
        /** Optional mTLS-terminating base URL; when null or empty the base configuration aliases are used. */
        private readonly ?string $mtlsBaseUrl = null,
    ) {
    }

    /**
     * Returns the mtls_endpoint_aliases map for inclusion in the discovery document.
     * When an mTLS base URL is set, all alias URLs are derived from it; otherwise the base aliases are returned.
     */
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
