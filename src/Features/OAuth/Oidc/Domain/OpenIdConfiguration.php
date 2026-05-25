<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Oidc\Domain;

/**
 * The complete OIDC Discovery Document for a tenant (OpenID Connect Discovery §3).
 *
 * Built per-tenant via `forTenant()`, which derives all endpoint URLs from the
 * base OAuth URL and tenant identifier. `toArray()` returns the JSON-serialisable
 * representation served at `/.well-known/openid-configuration`.
 * Contributors (via `DiscoveryContributorGateway`) may extend the document
 * with additional fields after construction.
 */
final class OpenIdConfiguration
{
    public function __construct(
        public readonly string $issuer,
        public readonly string $authorizationEndpoint,
        public readonly string $tokenEndpoint,
        public readonly string $introspectionEndpoint,
        public readonly string $userinfoEndpoint,
        public readonly string $endSessionEndpoint,
        public readonly string $jwksUri,
        public readonly string $checkSessionIframe,
        public readonly string $registrationEndpoint,
        public readonly string $revocationEndpoint,
        public readonly string $deviceAuthorizationEndpoint,
        public readonly string $pushedAuthorizationRequestEndpoint,
        public readonly string $backchannelAuthenticationEndpoint,
        public readonly MtlsEndpointAliases $mtlsEndpointAliases,
        public readonly array $grantTypesSupported,
        public readonly array $responseTypesSupported,
        public readonly array $responseModesSupported,
        public readonly array $scopesSupported,
        public readonly array $claimsSupported,
        public readonly array $subjectTypesSupported,
        public readonly array $codeChallengeMethodsSupported,
        public readonly array $tokenEndpointAuthMethodsSupported,
        public readonly array $acrValuesSupported,
        public readonly bool $requirePushedAuthorizationRequests,
        public readonly bool $backchannelLogoutSupported,
        public readonly bool $backchannelLogoutSessionSupported,
        public readonly bool $frontchannelLogoutSupported,
        public readonly bool $frontchannelLogoutSessionSupported,
        public readonly bool $authorizationResponseIssParameterSupported,
        public readonly bool $claimsParameterSupported,
        public readonly bool $requestParameterSupported,
        public readonly bool $requestUriParameterSupported,
    ) {
    }

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
