<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Oidc\Domain;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject\ResponseMode;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject\ResponseType;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject\UiLocale;
use Civi\Lughauth\Features\OAuth\Client\Domain\JwtAssertionVerifier;
use Civi\Lughauth\Shared\Security\AcrValues;

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
        /** response_type values accepted by AuthorizeHtml and issued by OidcResponseBuilder. */
        public readonly array $responseTypes,
        /** response_mode values AuthorizationResponseWriter delivers the authorization response in. */
        public readonly array $responseModes,
        /** Subject identifier types; pairwise is not implemented yet (056). */
        public readonly array $subjectTypes,
        /** PKCE methods accepted at /authorize and /token: only S256 (034). */
        public readonly array $codeChallengeMethods,
        /** Client authentication methods accepted by TokenController (ClientAuthenticator). */
        public readonly array $tokenEndpointAuthMethods,
        /** Client authentication methods accepted by IntrospectionController. */
        public readonly array $introspectionEndpointAuthMethods,
        /** Client authentication methods accepted by LogoutController::revoke. */
        public readonly array $revocationEndpointAuthMethods,
        /** Algorithms accepted for private_key_jwt client assertions at every endpoint (044). */
        public readonly array $endpointAuthSigningAlgs,
        /** Algorithms used by TokenSigner to sign ID tokens. */
        public readonly array $idTokenSigningAlgs,
        /** Algorithms accepted by RequestObjectValidator (JwtAssertionVerifier) for signed request objects. */
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
        /** acr values AuthorizeHtml steps up to and ClaimsMapper issues: the configured ones (AcrValues, 067). */
        public readonly array $acrValues = [],
    ) {
    }

    /**
     * Capabilities implemented by this server today. Change a line here when a feature lands.
     * PAR is announced as required only when the tenant's configuration requires it for every
     * client; a single client requiring it does not change discovery.
     */
    public static function implemented(bool $requirePushedAuthorizationRequests = false, ?AcrValues $acrValues = null): self
    {
        return new self(
            grantTypes: [
                'authorization_code',
                // Only through the hybrid response types, which RFC 7591 §2.1 ties to this grant.
                'implicit',
                'refresh_token',
                'client_credentials',
                'password',
                'urn:ietf:params:oauth:grant-type:device_code',
            ],
            responseTypes: ResponseType::SUPPORTED,
            responseModes: array_map(static fn (ResponseMode $mode): string => $mode->value, ResponseMode::cases()),
            subjectTypes: ['public'],
            codeChallengeMethods: ['S256'],
            tokenEndpointAuthMethods: ['client_secret_basic', 'client_secret_post', 'private_key_jwt', 'none'],
            introspectionEndpointAuthMethods: ['client_secret_basic', 'client_secret_post', 'private_key_jwt'],
            revocationEndpointAuthMethods: ['client_secret_basic', 'client_secret_post', 'private_key_jwt', 'none'],
            endpointAuthSigningAlgs: JwtAssertionVerifier::ALGORITHMS,
            idTokenSigningAlgs: ['RS256'],
            requestObjectSigningAlgs: JwtAssertionVerifier::ALGORITHMS,
            requestParameter: true,
            claimsParameter: false,
            authorizationResponseIssParameter: true,
            requirePushedAuthorizationRequests: $requirePushedAuthorizationRequests,
            backchannelLogout: true,
            frontchannelLogout: true,
            uiLocales: UiLocale::SUPPORTED,
            acrValues: ($acrValues ?? AcrValues::defaults())->supported(),
        );
    }
}
