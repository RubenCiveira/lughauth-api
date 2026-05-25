<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Session\Application;

final class CleanupResult
{
    /** @param array<string, string> $errors */
    public function __construct(
        public readonly \DateTimeImmutable $executedAt,
        public readonly array $errors,
    ) {
    }

    public function succeeded(): bool
    {
        return $this->errors === [];
    }
}
