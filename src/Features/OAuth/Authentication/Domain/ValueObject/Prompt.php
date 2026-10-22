<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject;

/**
 * The prompt parameter of an authorization request (OIDC Core §3.1.2.1) as a set of values.
 *
 * - none: no UI at all; the request fails with login_required, consent_required or
 *   interaction_required instead. It must stand alone.
 * - login: the user signs in again even with a valid session.
 * - select_account: handled as login, since a browser holds a single session per tenant: choosing
 *   another account means signing in with it.
 * - consent: the scope consent screen is shown again even when every scope was already granted.
 *
 * Unknown values are ignored, as the specification allows for extensions.
 */
final class Prompt
{
    public const string NONE = 'none';
    public const string LOGIN = 'login';
    public const string CONSENT = 'consent';
    public const string SELECT_ACCOUNT = 'select_account';

    /** @param list<string> $values */
    private function __construct(private readonly array $values)
    {
    }

    public static function fromString(?string $prompt): self
    {
        $values = preg_split('/\s+/', trim((string) $prompt), -1, PREG_SPLIT_NO_EMPTY);
        return new self($values === false ? [] : array_values(array_unique($values)));
    }

    public function has(string $value): bool
    {
        return in_array($value, $this->values, true);
    }

    /** False when none is combined with another value, which is an invalid_request. */
    public function isValid(): bool
    {
        return !$this->has(self::NONE) || count($this->values) === 1;
    }

    /** True when the user must sign in again whatever the state of the session. */
    public function forcesLogin(): bool
    {
        return $this->has(self::LOGIN) || $this->has(self::SELECT_ACCOUNT);
    }
}
