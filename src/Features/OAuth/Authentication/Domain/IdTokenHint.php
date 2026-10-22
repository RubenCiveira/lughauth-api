<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain;

/**
 * What a verified id_token_hint tells about the session it was issued from (IdTokenHintVerifier).
 */
final class IdTokenHint
{
    public function __construct(
        /** The user (sub claim). */
        public readonly string $subject,
        /** The public sid of the session; empty when the token carries none. */
        public readonly string $sid,
        /** The client the ID token was issued to (azp, or its single aud). */
        public readonly string $clientId,
    ) {
    }

    /**
     * True when the hint was issued from this session: same public sid, or the same user when the
     * token carries no sid.
     */
    public function isFor(string $publicSid, string $userId): bool
    {
        return $this->sid !== '' ? $this->sid === $publicSid : $this->subject === $userId;
    }
}
