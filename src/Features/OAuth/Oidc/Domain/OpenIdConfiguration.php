<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Oidc\Domain;

/**
 * Immutable value object representing the complete OIDC Discovery Document for a tenant (OpenID Connect Discovery §3).
 *
 * Built per-tenant via forTenant(), which derives all endpoint URLs from the base OAuth URL
 * and the tenant identifier, ensuring that each tenant receives its own correctly scoped
 * issuer and endpoint set. toArray() returns the full JSON-serialisable representation
 * served at /.well-known/openid-configuration, including all standard OpenID Provider
 * Metadata fields defined in RFC 8414 and OpenID Connect Discovery 1.0. DiscoveryContributorGateway
 * implementations may contribute additional fields (such as mTLS aliases) after construction
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
        /** URL of the check_session iframe used for silent OIDC session management. */
        public readonly string $checkSessionIframe,
        /** URL of the dynamic client registration endpoint (RFC 7591). */
        public readonly string $registrationEndpoint,
        /** URL of the token revocation endpoint (RFC 7009). */
        public readonly string $revocationEndpoint,
        /** URL of the Device Authorization endpoint (RFC 8628). */
        public readonly string $deviceAuthorizationEndpoint,
        /** URL of the Pushed Authorization Request endpoint (RFC 9126). */
        public readonly string $pushedAuthorizationRequestEndpoint,
        /** URL of the CIBA backchannel authentication endpoint. */
        public readonly string $backchannelAuthenticationEndpoint,
        /** mTLS endpoint aliases for RFC 8705 mutual-TLS client certificate authentication. */
        public readonly MtlsEndpointAliases $mtlsEndpointAliases,
        /** List of OAuth 2.0 grant types supported by this authorization server. */
        public readonly array $grantTypesSupported,
        /** List of OAuth 2.0 response types supported (code, token, id_token). */
        public readonly array $responseTypesSupported,
        /** List of OAuth 2.0 response modes supported (query, fragment, form_post). */
        public readonly array $responseModesSupported,
        /** List of OAuth 2.0 / OIDC scopes supported by this server. */
        public readonly array $scopesSupported,
        /** List of OIDC claim names that this server can include in tokens or UserInfo responses. */
        public readonly array $claimsSupported,
        /** List of subject identifier types supported (public, pairwise). */
        public readonly array $subjectTypesSupported,
        /** List of PKCE code challenge methods supported (plain, S256). */
        public readonly array $codeChallengeMethodsSupported,
        /** List of client authentication methods supported at the token endpoint. */
        public readonly array $tokenEndpointAuthMethodsSupported,
        /** List of ACR values supported for step-up authentication context. */
        public readonly array $acrValuesSupported,
        /** When true, all authorization requests must use the PAR endpoint (RFC 9126). */
        public readonly bool $requirePushedAuthorizationRequests,
        /** Whether the server supports OIDC Back-Channel Logout (OIDC Back-Channel Logout 1.0). */
        public readonly bool $backchannelLogoutSupported,
        /** Whether back-channel logout notifications include a sid claim for session binding. */
        public readonly bool $backchannelLogoutSessionSupported,
        /** Whether the server supports OIDC Front-Channel Logout. */
        public readonly bool $frontchannelLogoutSupported,
        /** Whether front-channel logout requests include iss and sid parameters. */
        public readonly bool $frontchannelLogoutSessionSupported,
        /** Whether the iss parameter is included in authorization responses (RFC 9207). */
        public readonly bool $authorizationResponseIssParameterSupported,
        /** Whether the claims request parameter is supported in authorization requests. */
        public readonly bool $claimsParameterSupported,
        /** Whether the request object parameter (JWT) is supported in authorization requests. */
        public readonly bool $requestParameterSupported,
        /** Whether the request_uri parameter is supported for passing request objects by reference. */
        public readonly bool $requestUriParameterSupported,
    ) {
    }

    /**
     * Builds a fully populated OpenIdConfiguration for the given tenant by deriving all endpoint
     * URLs from baseOAuthUrl and tenant. Capabilities and supported values are set to the defaults
     * advertised by this authorization server.
     */
    public static function forTenant(string $baseOAuthUrl, string $tenant): self
    {
        $base = "$baseOAuthUrl/openid/$tenant";
        return new self(
            issuer: $base,
            authorizationEndpoint: "$base/authorize",
            tokenEndpoint: "$base/token",
            introspectionEndpoint: "$base/introspect",
            userinfoEndpoint: "$base/userinfo",
            endSessionEndpoint: "$base/logout",
            jwksUri: "$base/jwks",
            checkSessionIframe: "$base/check-session",
            registrationEndpoint: "$base/register",
            revocationEndpoint: "$base/revoke",
            deviceAuthorizationEndpoint: "$base/device",
            pushedAuthorizationRequestEndpoint: "$base/par",
            backchannelAuthenticationEndpoint: "$base/backchannel",
            mtlsEndpointAliases: MtlsEndpointAliases::forBase($base),
            grantTypesSupported: [
                'authorization_code',
                'implicit',
                'refresh_token',
                'client_credentials',
                'password',
                'urn:ietf:params:oauth:grant-type:device_code',
            ],
            responseTypesSupported: ['code', 'token', 'id_token'],
            responseModesSupported: ['query', 'fragment', 'form_post'],
            scopesSupported: ['openid', 'profile', 'email'],
            claimsSupported: ['sub', 'iss', 'name', 'email'],
            subjectTypesSupported: ['public', 'pairwise'],
            codeChallengeMethodsSupported: ['plain', 'S256'],
            tokenEndpointAuthMethodsSupported: ['client_secret_basic', 'client_secret_post'],
            acrValuesSupported: ['urn:mace:incommon:iap:silver'],
            requirePushedAuthorizationRequests: true,
            backchannelLogoutSupported: true,
            backchannelLogoutSessionSupported: true,
            frontchannelLogoutSupported: true,
            frontchannelLogoutSessionSupported: true,
            authorizationResponseIssParameterSupported: true,
            claimsParameterSupported: true,
            requestParameterSupported: true,
            requestUriParameterSupported: false,
        );
    }

    /**
     * Serialises the configuration to the full JSON-serialisable array expected by the
     * /.well-known/openid-configuration endpoint, including all standard metadata fields.
     */
    public function toArray(): array
    {
        return [
            'issuer' => $this->issuer,
            'authorization_endpoint' => $this->authorizationEndpoint,
            'token_endpoint' => $this->tokenEndpoint,
            'introspection_endpoint' => $this->introspectionEndpoint,
            'userinfo_endpoint' => $this->userinfoEndpoint,
            'end_session_endpoint' => $this->endSessionEndpoint,
            'frontchannel_logout_supported' => $this->frontchannelLogoutSupported,
            'frontchannel_logout_session_supported' => $this->frontchannelLogoutSessionSupported,
            'jwks_uri' => $this->jwksUri,
            'check_session_iframe' => $this->checkSessionIframe,
            'registration_endpoint' => $this->registrationEndpoint,
            'revocation_endpoint' => $this->revocationEndpoint,
            'device_authorization_endpoint' => $this->deviceAuthorizationEndpoint,
            'pushed_authorization_request_endpoint' => $this->pushedAuthorizationRequestEndpoint,
            'backchannel_authentication_endpoint' => $this->backchannelAuthenticationEndpoint,
            'grant_types_supported' => $this->grantTypesSupported,
            'response_types_supported' => $this->responseTypesSupported,
            'response_modes_supported' => $this->responseModesSupported,
            'scopes_supported' => $this->scopesSupported,
            'claims_supported' => $this->claimsSupported,
            'claim_types_supported' => ['normal'],
            'subject_types_supported' => $this->subjectTypesSupported,
            'id_token_signing_alg_values_supported' => ['RS256'],
            'id_token_encryption_alg_values_supported' => ['RSA-OAEP'],
            'id_token_encryption_enc_values_supported' => ['A256GCM'],
            'userinfo_signing_alg_values_supported' => ['RS256'],
            'userinfo_encryption_alg_values_supported' => ['RSA-OAEP'],
            'userinfo_encryption_enc_values_supported' => ['A256GCM'],
            'request_object_signing_alg_values_supported' => ['RS256', 'ES256', 'PS256'],
            'request_object_encryption_alg_values_supported' => ['RSA-OAEP'],
            'request_object_encryption_enc_values_supported' => ['A256GCM'],
            'token_endpoint_auth_methods_supported' => $this->tokenEndpointAuthMethodsSupported,
            'token_endpoint_auth_signing_alg_values_supported' => ['RS256'],
            'introspection_endpoint_auth_methods_supported' => ['client_secret_basic'],
            'introspection_endpoint_auth_signing_alg_values_supported' => ['RS256'],
            'revocation_endpoint_auth_methods_supported' => ['client_secret_basic'],
            'revocation_endpoint_auth_signing_alg_values_supported' => ['RS256'],
            'authorization_signing_alg_values_supported' => ['RS256'],
            'authorization_encryption_alg_values_supported' => ['RSA-OAEP'],
            'authorization_encryption_enc_values_supported' => ['A256GCM'],
            'backchannel_token_delivery_modes_supported' => ['poll', 'ping'],
            'backchannel_authentication_request_signing_alg_values_supported' => ['RS256'],
            'acr_values_supported' => $this->acrValuesSupported,
            'code_challenge_methods_supported' => $this->codeChallengeMethodsSupported,
            'tls_client_certificate_bound_access_tokens' => false,
            'require_pushed_authorization_requests' => $this->requirePushedAuthorizationRequests,
            'backchannel_logout_supported' => $this->backchannelLogoutSupported,
            'backchannel_logout_session_supported' => $this->backchannelLogoutSessionSupported,
            'claims_parameter_supported' => $this->claimsParameterSupported,
            'request_parameter_supported' => $this->requestParameterSupported,
            'request_uri_parameter_supported' => $this->requestUriParameterSupported,
            'require_request_uri_registration' => false,
            'authorization_response_iss_parameter_supported' => $this->authorizationResponseIssParameterSupported,
            'mtls_endpoint_aliases' => $this->mtlsEndpointAliases->toArray(),
        ];
    }
}
