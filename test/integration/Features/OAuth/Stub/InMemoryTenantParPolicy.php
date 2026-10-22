<?php

declare(strict_types=1);

namespace Tests\integration\Features\OAuth\Stub;

use Civi\Lughauth\Features\OAuth\Par\Domain\Gateway\TenantParPolicyGateway;

class InMemoryTenantParPolicy implements TenantParPolicyGateway
{
    /** @var array<string, true> names of the tenants that require PAR */
    private array $requiring = [];

    public function requirePar(string $tenant): void
    {
        $this->requiring[$tenant] = true;
    }

    public function requiresPar(string $tenant): bool
    {
        return isset($this->requiring[$tenant]);
    }
}
