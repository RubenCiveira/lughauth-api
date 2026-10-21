<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject;

/**
 * OAuth scope as a set of exact values (RFC 6749 §3.3).
 *
 * The scope string is split on whitespace, so `myprofile` is a scope of its own and never grants
 * `profile`. Duplicates are dropped and the first-seen order is kept, so the normalised string is
 * stable for the tokens and the token response.
 */
final class ScopeSet
{
    /** @param list<string> $values */
    private function __construct(private readonly array $values)
    {
    }

    public static function fromString(?string $scope): self
    {
        $values = preg_split('/\s+/', trim((string) $scope), -1, PREG_SPLIT_NO_EMPTY);
        return new self($values === false ? [] : array_values(array_unique($values)));
    }

    /** Returns true when the exact scope value was granted. */
    public function has(string $scope): bool
    {
        return in_array($scope, $this->values, true);
    }

    /** Returns true when every value of this set is also in $other. */
    public function isSubsetOf(self $other): bool
    {
        return array_diff($this->values, $other->values) === [];
    }

    public function isEmpty(): bool
    {
        return $this->values === [];
    }

    /** @return list<string> */
    public function values(): array
    {
        return $this->values;
    }

    /** Space-separated form used in tokens and responses. */
    public function toString(): string
    {
        return implode(' ', $this->values);
    }
}
