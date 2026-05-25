<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Oidc\Domain;

final class MtlsEndpointAliases
{
    public function __construct(
        public readonly string $tokenEndpoint,
        public readonly string $revocationEndpoint,
        public readonly string $introspectionEndpoint,
        public readonly string $deviceAuthorizationEndpoint,
        public readonly string $registrationEndpoint,
        public readonly string $userinfoEndpoint,
        public readonly string $pushedAuthorizationRequestEndpoint,
        public readonly string $backchannelAuthenticationEndpoint,
    ) {
    }

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
