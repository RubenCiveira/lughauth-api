<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Application;

use Civi\Lughauth\Shared\Context;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\IdTokenHint;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\TokenSigner;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\TokenType;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\VerificationPolicy;

/**
 * Verifies an id_token_hint (RP-initiated logout, and later prompt=none and step-up, EP-09).
 *
 * The hint must be an ID token signed by this server for the tenant (its own key, the ID token
 * type and the tenant's issuer) and addressed to the client it names. It may have expired: an
 * RP usually sends the last ID token it got, so only its signature and claims are trusted.
 */
class IdTokenHintVerifier
{
    public function __construct(
        private readonly TokenSigner $keys,
        private readonly Context $context,
    ) {
    }

    /**
     * Returns the hint's user, sid and client, or null when it is forged, of another tenant or
     * not an ID token, or when $clientId is given and the token was not issued to it.
     */
    public function verify(string $tenant, string $hint, ?string $clientId = null): ?IdTokenHint
    {
        $payload = $this->keys->verify($tenant, $hint, VerificationPolicy::expecting(TokenType::Id)->ignoringTime());
        if ($payload === null || ($payload['iss'] ?? null) !== $this->context->getIssuer($tenant)) {
            return null;
        }
        $audiences = array_values(array_filter((array) ($payload['aud'] ?? []), 'is_string'));
        $client = (string) ($payload['azp'] ?? (count($audiences) === 1 ? $audiences[0] : ''));
        if ($client === '' || !in_array($client, $audiences, true) || ($clientId !== null && $clientId !== $client)) {
            return null;
        }
        return new IdTokenHint(
            subject: (string) ($payload['sub'] ?? ''),
            sid: (string) ($payload['sid'] ?? ''),
            clientId: $client,
        );
    }
}
