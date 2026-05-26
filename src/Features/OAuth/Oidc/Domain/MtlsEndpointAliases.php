<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Oidc\Domain;

/**
 * Value object that holds the mTLS (Mutual TLS) endpoint alias URLs for a tenant (RFC 8705).
 *
 * RFC 8705 requires that the discovery document advertise alternate endpoint URLs intended
 * for clients that authenticate using mTLS client certificates. These aliases point to the
 * mTLS-terminating reverse proxy rather than the standard OAuth base URL so that mutual-TLS
 * clients can discover the correct host. The forBase() factory derives all alias URLs from a
 * single base string, keeping alias construction consistent with the main endpoint derivation
 * in OpenIdConfiguration::forTenant(). toArray() returns the snake_case map expected by the
 * mtls_endpoint_aliases field in the discovery document.
 */
final class MtlsEndpointAliases
{
    public function __construct(
        /** mTLS-specific URL for the token endpoint. */
        public readonly string $tokenEndpoint,
        /** mTLS-specific URL for the token revocation endpoint. */
        public readonly string $revocationEndpoint,
        /** mTLS-specific URL for the token introspection endpoint. */
        public readonly string $introspectionEndpoint,
        /** mTLS-specific URL for the device authorization endpoint (RFC 8628). */
        public readonly string $deviceAuthorizationEndpoint,
        /** mTLS-specific URL for the dynamic client registration endpoint. */
        public readonly string $registrationEndpoint,
        /** mTLS-specific URL for the UserInfo endpoint. */
        public readonly string $userinfoEndpoint,
        /** mTLS-specific URL for the Pushed Authorization Request endpoint (RFC 9126). */
        public readonly string $pushedAuthorizationRequestEndpoint,
        /** mTLS-specific URL for the CIBA backchannel authentication endpoint. */
        public readonly string $backchannelAuthenticationEndpoint,
    ) {
    }

    /**
     * Derives all mTLS alias URLs from the given base path by appending the standard endpoint suffixes.
     * Intended to be called with the same per-tenant base string used by OpenIdConfiguration::forTenant().
     */
    public static function forBase(string $base): self
    {
        return new self(
            tokenEndpoint: "$base/token",
            revocationEndpoint: "$base/revoke",
            introspectionEndpoint: "$base/introspect",
            deviceAuthorizationEndpoint: "$base/device",
            registrationEndpoint: "$base/register",
            userinfoEndpoint: "$base/userinfo",
            pushedAuthorizationRequestEndpoint: "$base/par",
            backchannelAuthenticationEndpoint: "$base/backchannel",
        );
    }

    /**
     * Returns the snake_case associative array matching the mtls_endpoint_aliases schema
     * expected in the OpenID Connect Discovery Document.
     */
    public function toArray(): array
    {
        return [
            'token_endpoint' => $this->tokenEndpoint,
            'revocation_endpoint' => $this->revocationEndpoint,
            'introspection_endpoint' => $this->introspectionEndpoint,
            'device_authorization_endpoint' => $this->deviceAuthorizationEndpoint,
            'registration_endpoint' => $this->registrationEndpoint,
            'userinfo_endpoint' => $this->userinfoEndpoint,
            'pushed_authorization_request_endpoint' => $this->pushedAuthorizationRequestEndpoint,
            'backchannel_authentication_endpoint' => $this->backchannelAuthenticationEndpoint,
        ];
    }
}
