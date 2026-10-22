<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject;

/**
 * OAuth response_type as a set of values (OAuth 2.0 Multiple Response Type Encoding Practices §5).
 *
 * The order of the values carries no meaning, so "id_token code" and "code id_token" are the same
 * response type: they are normalised to the order of ORDER (unknown values last), which is the
 * form compared with SUPPORTED and with the response types registered for the client.
 */
final class ResponseType
{
    /**
     * Response types /authorize issues: the code flow and the hybrid flows. The implicit flow
     * ("token", "id_token" and "id_token token" without a code) is retired, as in OAuth 2.1 (EP-08).
     */
    public const array SUPPORTED = ['code', 'code id_token', 'code token', 'code id_token token'];

    private const array ORDER = ['code', 'id_token', 'token'];

    /** @param list<string> $values */
    private function __construct(private readonly array $values)
    {
    }

    public static function fromString(string $responseType): self
    {
        $values = array_values(array_unique(preg_split('/\s+/', trim($responseType), -1, PREG_SPLIT_NO_EMPTY) ?: []));
        $rank = static fn (string $value): int => ($index = array_search($value, self::ORDER, true)) === false ? count(self::ORDER) : $index;
        usort($values, static fn (string $a, string $b): int => [$rank($a), $a] <=> [$rank($b), $b]);
        return new self($values);
    }

    public function has(string $value): bool
    {
        return in_array($value, $this->values, true);
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    public function isSupported(): bool
    {
        return in_array($this->toString(), self::SUPPORTED, true);
    }

    /** True when it is one of the given response types (e.g. those registered for the client), in any order. */
    public function isAmong(array $responseTypes): bool
    {
        foreach ($responseTypes as $responseType) {
            if (is_string($responseType) && self::fromString($responseType)->toString() === $this->toString()) {
                return true;
            }
        }
        return false;
    }

    /** True when the response carries an ID or access token, which must never travel in the query. */
    public function issuesTokens(): bool
    {
        return $this->has('id_token') || $this->has('token');
    }

    public function toString(): string
    {
        return implode(' ', $this->values);
    }
}
