<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\SimpleCache\CacheInterface;
use Civi\Lughauth\Shared\Context;
use Civi\Lughauth\Shared\Exception\UnauthorizedException;
use Civi\Lughauth\Features\Access\Tenant\Domain\Gateway\TenantQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\Tenant\Domain\Tenant;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Application\AccessTokenPolicy;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\TokenRevocationGateway;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\TokenSigner;

final class AccessTokenPolicyUnitTest extends TestCase
{
    public function testAcceptsTheTenantByNameOrUid(): void
    {
        $policy = $this->policy();

        $policy->check($this->claims(['tenant' => 'acme']), 'acme');
        $policy->check($this->claims(['tenant' => 'acme-uid']), 'acme');

        $this->addToAssertionCount(2);
    }

    /** @return array<string, array{array<string, mixed>, list<string>}> */
    public static function rejected(): array
    {
        return [
            'expired' => [['exp' => time() - 1], []],
            'not yet valid' => [['nbf' => time() + 60], []],
            'foreign issuer' => [['iss' => 'https://idp/oauth/openid/other'], []],
            'no audience' => [['aud' => null], []],
            'none of the audiences' => [['aud' => ['a', 'b']], ['c']],
            'no tenant' => [['tenant' => null], []],
            'empty tenant' => [['tenant' => ''], []],
            'another tenant' => [['tenant' => 'other-uid'], []],
            'no jti' => [['jti' => null], []],
            'revoked' => [['jti' => 'revoked'], []],
        ];
    }

    /**
     * @param array<string, mixed> $override
     * @param list<string> $audiences
     */
    #[DataProvider('rejected')]
    public function testRejects(array $override, array $audiences): void
    {
        $this->expectException(UnauthorizedException::class);
        $this->policy()->check($this->claims($override), 'acme', $audiences);
    }

    public function testRechecksTheExpiryOfCachedClaims(): void
    {
        $signer = $this->createMock(TokenSigner::class);
        $signer->expects($this->once())->method('verify')->willReturn($this->claims(['exp' => time() + 1]));
        $cache = $this->cache();
        $policy = $this->policy($signer, $cache);
        $policy->verify('acme', 'token');

        // The signature is not verified again, but the cached claims are already expired.
        $this->expectException(UnauthorizedException::class);
        $cache->items = array_map(fn (string $json) => str_contains($json, '"exp"') ? json_encode([...json_decode($json, true), 'exp' => time() - 1]) : $json, $cache->items);
        $policy->verify('acme', 'token');
    }

    public function testResolvesTheTenantOfACanonicalIssuer(): void
    {
        $policy = $this->policy();

        $this->assertSame('acme', $policy->tenantOfIssuer('https://idp/oauth/openid/acme'));
        $this->assertNull($policy->tenantOfIssuer('https://other/oauth/openid/acme'));
        $this->assertNull($policy->tenantOfIssuer('https://idp/oauth/openid/acme/extra'));
        $this->assertNull($policy->tenantOfIssuer('https://idp/oauth/openid/'));
    }

    /** @param array<string, mixed> $override */
    private function claims(array $override = []): array
    {
        return array_filter([
            'iss' => 'https://idp/oauth/openid/acme',
            'aud' => ['client', 'api'],
            'tenant' => 'acme-uid',
            'jti' => 'jti-1',
            'nbf' => time() - 10,
            'exp' => time() + 600,
            ...$override,
        ], static fn (mixed $value): bool => $value !== null);
    }

    private function policy(?TokenSigner $signer = null, ?CacheInterface $cache = null): AccessTokenPolicy
    {
        $revocations = $this->createStub(TokenRevocationGateway::class);
        $revocations->method('isRevoked')->willReturnCallback(fn (string $jti, string $tenant) => $jti === 'revoked' && $tenant === 'acme');
        $tenant = $this->createStub(Tenant::class);
        $tenant->method('uid')->willReturn('acme-uid');
        $tenants = $this->createStub(TenantQueryRepositoryGateway::class);
        $tenants->method('findOneByName')->willReturnCallback(fn (string $name) => $name === 'acme' ? $tenant : null);
        $context = $this->createStub(Context::class);
        $context->method('getIssuer')->willReturnCallback(fn (string $name): string => 'https://idp/oauth/openid/' . $name);

        return new AccessTokenPolicy($signer ?? $this->createStub(TokenSigner::class), $revocations, $tenants, $context, $cache ?? $this->cache());
    }

    private function cache(): CacheInterface
    {
        return new class () implements CacheInterface {
            /** @var array<string, mixed> */
            public array $items = [];

            public function get(string $key, mixed $default = null): mixed
            {
                return $this->items[$key] ?? $default;
            }

            public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
            {
                $this->items[$key] = $value;
                return true;
            }

            public function delete(string $key): bool
            {
                unset($this->items[$key]);
                return true;
            }

            public function clear(): bool
            {
                $this->items = [];
                return true;
            }

            public function getMultiple(iterable $keys, mixed $default = null): iterable
            {
                return [];
            }

            public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
            {
                return true;
            }

            public function deleteMultiple(iterable $keys): bool
            {
                return true;
            }

            public function has(string $key): bool
            {
                return isset($this->items[$key]);
            }
        };
    }
}
