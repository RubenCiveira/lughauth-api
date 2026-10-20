<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\TokenSecurity\Domain;

/**
 * What a consumer requires from a token before using its claims.
 *
 * For now it holds the accepted purposes and whether the time window (nbf/exp) is enforced;
 * issuer, audience and tenant checks will be added here by the bearer validation work (EP-06).
 */
final class VerificationPolicy
{
    /**
     * @param list<TokenType> $types
     */
    private function __construct(
        public readonly array $types,
        public readonly bool $checkTime,
    ) {
    }

    /**
     * Accepts only tokens of the given purposes, inside their time window.
     */
    public static function expecting(TokenType $type, TokenType ...$others): self
    {
        return new self([$type, ...$others], true);
    }

    /**
     * Same purposes, but expired or not-yet-valid tokens are still accepted.
     * Only for flows that must read old tokens: revocation and `id_token_hint`.
     */
    public function ignoringTime(): self
    {
        return new self($this->types, false);
    }

    public function accepts(TokenType $type): bool
    {
        return in_array($type, $this->types, true);
    }
}
