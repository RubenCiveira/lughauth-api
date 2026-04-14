<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\Oidc\Authentication\Domain;

use Civi\Lughauth\Features\Oidc\Key\Domain\Gateway\TokenSigner;

/**
 * Builds OIDC Back-Channel Logout tokens (logout+jwt) per spec:
 * https://openid.net/specs/openid-connect-backchannel-1_0.html
 *
 * NOTE: The spec forbids the 'nonce' claim in logout tokens.
 * The 'typ' claim is overridden to 'logout+jwt' to distinguish these
 * tokens from regular access/id tokens.
 */
final class LogoutToken
{
    private function __construct()
    {
    }

    public static function build(
        TokenSigner $signer,
        string $tenant,
        string $issuer,
        string $subject,
        string $clientId,
        string $sessionId,
    ): string {
        return $signer->sign($tenant, [
            'typ'    => 'logout+jwt',
            'iss'    => $issuer,
            'sub'    => $subject,
            'aud'    => $clientId,
            'sid'    => $sessionId,
            'events' => ['http://schemas.openid.net/event/backchannel-logout' => new \stdClass()],
        ], new \DateInterval('PT2M'));
    }
}
