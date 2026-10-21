<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Civi\Lughauth\Features\Access\Tenant\Domain\Gateway\TenantQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\Tenant\Domain\Tenant;
use Civi\Lughauth\Features\Access\TenantConfig\Domain\Gateway\TenantConfigQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\TenantConfig\Domain\TenantConfig;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\TokenLifetimes;
use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driven\TenantConfigTokenLifetimesAdapter;

final class TokenLifetimesUnitTest extends TestCase
{
    public function test_non_positive_values_fall_back_to_the_defaults(): void
    {
        $lifetimes = new TokenLifetimes(refreshSeconds: 0, refreshFamilySeconds: -1);

        $this->assertSame(TokenLifetimes::REFRESH_SECONDS, $this->seconds($lifetimes->refresh));
        $this->assertSame(TokenLifetimes::REFRESH_FAMILY_SECONDS, $this->seconds($lifetimes->refreshFamily));
    }

    public function test_refresh_lifetimes_come_from_the_tenant_configuration(): void
    {
        $config = $this->createStub(TenantConfig::class);
        $config->method('getRefreshTokenTtlSeconds')->willReturn(120);
        $config->method('getRefreshTokenMaxLifetimeSeconds')->willReturn(3600);

        $lifetimes = $this->adapter($config)->forTenant('tenant1');

        $this->assertSame(120, $this->seconds($lifetimes->refresh));
        $this->assertSame(3600, $this->seconds($lifetimes->refreshFamily));
        $this->assertSame(TokenLifetimes::ACCESS_SECONDS, $this->seconds($lifetimes->access));
    }

    public function test_tenant_without_configuration_gets_the_defaults(): void
    {
        $lifetimes = $this->adapter(null)->forTenant('tenant1');

        $this->assertSame(TokenLifetimes::REFRESH_SECONDS, $this->seconds($lifetimes->refresh));
    }

    private function adapter(?TenantConfig $config): TenantConfigTokenLifetimesAdapter
    {
        $tenants = $this->createStub(TenantQueryRepositoryGateway::class);
        $tenants->method('findOneByName')->willReturn($this->createStub(Tenant::class));
        $configs = $this->createStub(TenantConfigQueryRepositoryGateway::class);
        $configs->method('findOneByTenant')->willReturn($config);
        return new TenantConfigTokenLifetimesAdapter($tenants, $configs);
    }

    private function seconds(DateInterval $interval): int
    {
        return (new DateTimeImmutable('@0'))->add($interval)->getTimestamp();
    }
}
