<?php

declare(strict_types=1);

namespace Civi\Lughauth\Shared\Security;

/**
 * Authentication assurance levels this server issues and verifies, ordered by the assurance they
 * give (task 067). The acr values that name them are configuration (AcrValues), so they can match
 * those of the identity providers and relying parties this server works with.
 *
 * The level is derived from the amr actually recorded for the session (RFC 8176), never stored on
 * its own: any authenticated session is single factor, and one whose amr includes `mfa` is multi
 * factor. A resource server compares the level of the access token with the one an operation
 * needs (AuthenticationContext::requireAcr).
 */
enum Acr
{
    /** The user proved one factor: password, passkey, federated login, recovery or registration code. */
    case SINGLE_FACTOR;

    /** The user also proved a second factor (TOTP or recovery code). */
    case MULTI_FACTOR;

    /**
     * Level of a session from the amr recorded with it.
     *
     * @param string[] $amr
     */
    public static function fromAmr(array $amr): self
    {
        return in_array('mfa', $amr, true) ? self::MULTI_FACTOR : self::SINGLE_FACTOR;
    }

    /** True when this level gives at least the assurance of $required. */
    public function satisfies(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }

    /** Configuration key of the acr values of this level (AcrValues). */
    public function configKey(): string
    {
        return match ($this) {
            self::SINGLE_FACTOR => 'security.acr.single-factor',
            self::MULTI_FACTOR => 'security.acr.multi-factor',
        };
    }

    /** acr value issued for this level when the configuration names none. */
    public function defaultValue(): string
    {
        return match ($this) {
            self::SINGLE_FACTOR => 'urn:lugh:acr:1fa',
            self::MULTI_FACTOR => 'urn:lugh:acr:mfa',
        };
    }

    private function rank(): int
    {
        return match ($this) {
            self::SINGLE_FACTOR => 1,
            self::MULTI_FACTOR => 2,
        };
    }
}
