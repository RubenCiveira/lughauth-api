<?php

declare(strict_types=1);

namespace Tests\integration\Features\OAuth\Stub;

use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\ReplayJtiGateway;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\ReplayPurpose;

class InMemoryReplayJti implements ReplayJtiGateway
{
    /** @var array<string, \DateTimeImmutable> */
    private array $consumed = [];

    public function consume(string $tenant, string $clientId, ReplayPurpose $purpose, string $jti, \DateTimeImmutable $expiresAt): bool
    {
        $key = implode("\n", [$tenant, $clientId, $purpose->value, $jti]);
        if (isset($this->consumed[$key])) {
            return false;
        }
        $this->consumed[$key] = $expiresAt;
        return true;
    }

    public function release(string $tenant, string $clientId, ReplayPurpose $purpose, string $jti): void
    {
        unset($this->consumed[implode("\n", [$tenant, $clientId, $purpose->value, $jti])]);
    }

    public function purgeExpired(): void
    {
        $now = new \DateTimeImmutable();
        $this->consumed = array_filter($this->consumed, static fn (\DateTimeImmutable $expiresAt): bool => $expiresAt >= $now);
    }
}
