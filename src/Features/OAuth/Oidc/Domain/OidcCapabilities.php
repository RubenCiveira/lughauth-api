<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Oidc\Domain;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject\UiLocale;

/**
 * Immutable set of protocol capabilities the authorization server actually implements.
 *
 * The discovery document is derived from this object so that it never advertises a grant,
 * response type, algorithm or flag without code behind it. Each value in implemented()
 * must be backed by a route or a code path; an epic that adds a protocol enables it by
 * changing the matching line there, and DiscoveryTest checks the result against OAuthPlugin.
 */
final class OidcCapabilities
{
    public function __construct(
        /** OAuth 2.0 grant types accepted by the token endpoint (TokenController). */
        public readonly array $grantTypes,
        /** response_type values handled by OidcResponseBuilder. */
        public readonly array $responseTypes,
        /** response_mode values used to deliver the authorization response. */
        public readonly array $responseModes,
        /** Subject identifier types; pairwise is not implemented yet (056). */
        public readonly array $subjectTypes,
        /** PKCE methods accepted at /authorize and /token: only S256 (034). */
        public readonly array $codeChallengeMethods,
        /** Client authentication methods accepted by TokenController. */
        public readonly array $tokenEndpointAuthMethods,
        /** Client authentication methods accepted by IntrospectionController. */
        public readonly array $introspectionEndpointAuthMethods,
        /** Client authentication methods accepted by LogoutController::revoke. */
        public readonly array $revocationEndpointAuthMethods,
        /** Algorithms used by TokenSigner to sign ID tokens. */
        public readonly array $idTokenSigningAlgs,
        /** Algorithms accepted by RequestObjectValidator for signed request objects. */
        public readonly array $requestObjectSigningAlgs,
        /** Whether request objects passed by value ("request") are validated. */
        public readonly bool $requestParameter,
        /** Whether the "claims" authorization request parameter is honoured (061). */
        public readonly bool $claimsParameter,
        /** Whether authorization responses carry the iss parameter (RFC 9207, 037). */
        public readonly bool $authorizationResponseIssParameter,
        /** Whether every authorization request of the tenant must go through PAR (046). */
        public readonly bool $requirePushedAuthorizationRequests,
        /** Whether OIDC Back-Channel Logout notifications are sent with a sid claim. */
        public readonly bool $backchannelLogout,
        /** Whether OIDC Front-Channel Logout iframes are rendered with iss and sid. */
        public readonly bool $frontchannelLogout,
        /** Languages the login UI can be shown in through ui_locales (UiLocale, 068). */
        public readonly array $uiLocales = [],
    ) {
    }

    /**
     * Capabilities implemented by this server today. Change a line here when a feature lands.
     * PAR is announced as required only when the tenant's configuration requires it for every
     * client; a single client requiring it does not change discovery.
     */
    public static function implemented(bool $requirePushedAuthorizationRequests = false): self
    {
        return new self(
            grantTypes: [
                'authorization_code',
                'implicit',
                'refresh_token',
                'client_credentials',
                'password',
                'urn:ietf:params:oauth:grant-type:device_code',
            ],
            responseTypes: ['code', 'token', 'id_token'],
            responseModes: ['query', 'fragment'],
            subjectTypes: ['public'],
            codeChallengeMethods: ['S256'],
            tokenEndpointAuthMethods: ['client_secret_basic', 'client_secret_post', 'none'],
            introspectionEndpointAuthMethods: ['client_secret_basic', 'client_secret_post'],
            revocationEndpointAuthMethods: ['client_secret_basic', 'client_secret_post', 'none'],
            idTokenSigningAlgs: ['RS256'],
            requestObjectSigningAlgs: ['RS256', 'ES256', 'PS256'],
            requestParameter: true,
            claimsParameter: false,
            authorizationResponseIssParameter: false,
            requirePushedAuthorizationRequests: $requirePushedAuthorizationRequests,
            backchannelLogout: true,
            frontchannelLogout: true,
            uiLocales: UiLocale::SUPPORTED,
        );
    }
}
