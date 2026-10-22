<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Oidc\Domain;

/**
 * Immutable value object representing the complete OIDC Discovery Document for a tenant (OpenID Connect Discovery §3).
 *
 * Built per-tenant via forIssuer(), which derives all endpoint URLs from the canonical tenant
 * issuer, ensuring that each tenant receives its own correctly scoped endpoint set. Supported
 * values and flags come from OidcCapabilities so that only implemented features are advertised.
 * toArray() returns the JSON-serialisable representation served at /.well-known/openid-configuration.
 * DiscoveryContributorGateway implementations may contribute additional fields (such as mTLS aliases)
 * by merging their result onto the toArray() output before the final response is written.
 */
final class OpenIdConfiguration
{
    public function __construct(
        /** The issuer identifier URL for this tenant, used as the "iss" claim in all tokens. */
        public readonly string $issuer,
        /** URL of the OAuth 2.0 / OIDC authorization endpoint where user authentication begins. */
        public readonly string $authorizationEndpoint,
        /** URL of the token endpoint used to exchange codes and refresh tokens for access tokens. */
        public readonly string $tokenEndpoint,
        /** URL of the token introspection endpoint (RFC 7662) for validating opaque tokens. */
        public readonly string $introspectionEndpoint,
        /** URL of the UserInfo endpoint returning claims about the authenticated end-user. */
        public readonly string $userinfoEndpoint,
        /** URL of the end-session endpoint (OIDC RP-Initiated Logout) for signing out users. */
        public readonly string $endSessionEndpoint,
        /** URL of the JSON Web Key Set endpoint exposing public keys for token signature verification. */
        public readonly string $jwksUri,
        /** URL of the dynamic client registration endpoint (RFC 7591). */
        public readonly string $registrationEndpoint,
        /** URL of the token revocation endpoint (RFC 7009). */
        public readonly string $revocationEndpoint,
        /** URL of the Device Authorization endpoint (RFC 8628). */
        public readonly string $deviceAuthorizationEndpoint,
        /** URL of the Pushed Authorization Request endpoint (RFC 9126). */
        public readonly string $pushedAuthorizationRequestEndpoint,
        /** List of OAuth 2.0 / OIDC scopes supported by this server. */
        public readonly array $scopesSupported,
        /** List of OIDC claim names that this server can include in tokens or UserInfo responses. */
        public readonly array $claimsSupported,
        /** Protocol capabilities from which supported values and flags are derived. */
        public readonly OidcCapabilities $capabilities,
    ) {
    }

    /**
     * Builds a fully populated OpenIdConfiguration for the given canonical issuer
     * (e.g. Context::getIssuer()), with every endpoint URL placed under it.
     */
    public static function forIssuer(string $issuer, OidcCapabilities $capabilities): self
    {
        return new self(
            issuer: $issuer,
            authorizationEndpoint: "$issuer/authorize",
            tokenEndpoint: "$issuer/token",
            introspectionEndpoint: "$issuer/introspect",
            userinfoEndpoint: "$issuer/userinfo",
            endSessionEndpoint: "$issuer/logout",
            jwksUri: "$issuer/jwks",
            registrationEndpoint: "$issuer/register",
            revocationEndpoint: "$issuer/revoke",
            deviceAuthorizationEndpoint: "$issuer/device",
            pushedAuthorizationRequestEndpoint: "$issuer/par",
            scopesSupported: ['openid', 'profile', 'email'],
            claimsSupported: ['sub', 'iss', 'aud', 'auth_time', 'amr', 'sid', 'name', 'email'],
            capabilities: $capabilities,
        );
    }

    /**
     * Serialises the configuration to the JSON-serialisable array expected by the
     * /.well-known/openid-configuration endpoint.
     */
    public function toArray(): array
    {
        $caps = $this->capabilities;
        return [
            'issuer' => $this->issuer,
            'authorization_endpoint' => $this->authorizationEndpoint,
            'token_endpoint' => $this->tokenEndpoint,
            'introspection_endpoint' => $this->introspectionEndpoint,
            'userinfo_endpoint' => $this->userinfoEndpoint,
            'end_session_endpoint' => $this->endSessionEndpoint,
            'frontchannel_logout_supported' => $caps->frontchannelLogout,
            'frontchannel_logout_session_supported' => $caps->frontchannelLogout,
            'jwks_uri' => $this->jwksUri,
            'registration_endpoint' => $this->registrationEndpoint,
            'revocation_endpoint' => $this->revocationEndpoint,
            'device_authorization_endpoint' => $this->deviceAuthorizationEndpoint,
            'pushed_authorization_request_endpoint' => $this->pushedAuthorizationRequestEndpoint,
            'grant_types_supported' => $caps->grantTypes,
            'response_types_supported' => $caps->responseTypes,
            'response_modes_supported' => $caps->responseModes,
            'scopes_supported' => $this->scopesSupported,
            'claims_supported' => $this->claimsSupported,
            'claim_types_supported' => ['normal'],
            'subject_types_supported' => $caps->subjectTypes,
            'id_token_signing_alg_values_supported' => $caps->idTokenSigningAlgs,
            'request_object_signing_alg_values_supported' => $caps->requestObjectSigningAlgs,
            'token_endpoint_auth_methods_supported' => $caps->tokenEndpointAuthMethods,
            'introspection_endpoint_auth_methods_supported' => $caps->introspectionEndpointAuthMethods,
            'revocation_endpoint_auth_methods_supported' => $caps->revocationEndpointAuthMethods,
            'code_challenge_methods_supported' => $caps->codeChallengeMethods,
            'tls_client_certificate_bound_access_tokens' => false,
            'require_pushed_authorization_requests' => $caps->requirePushedAuthorizationRequests,
            'backchannel_logout_supported' => $caps->backchannelLogout,
            'backchannel_logout_session_supported' => $caps->backchannelLogout,
            'claims_parameter_supported' => $caps->claimsParameter,
            'ui_locales_supported' => $caps->uiLocales,
            'request_parameter_supported' => $caps->requestParameter,
            'request_uri_parameter_supported' => false,
            'require_request_uri_registration' => false,
            'authorization_response_iss_parameter_supported' => $caps->authorizationResponseIssParameter,
        ];
    }
}
