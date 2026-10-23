<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Civi\Lughauth\Shared\Exception\ConstraintException;
use Civi\Lughauth\Features\Access\Tenant\Domain\TenantRef;
use Civi\Lughauth\Features\Access\TenantLoginProvider\Domain\Gateway\TenantLoginProviderQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\TenantLoginProvider\Domain\TenantLoginProvider;
use Civi\Lughauth\Features\Access\TenantLoginProvider\Domain\TenantLoginProviderRef;
use Civi\Lughauth\Features\Access\User\Domain\Gateway\UserQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\User\Domain\User;
use Civi\Lughauth\Features\Access\User\Domain\UserRef;
use Civi\Lughauth\Features\Access\UserFederatedIdentity\Application\Usecase\Create\UserFederatedIdentityCreateCheck;
use Civi\Lughauth\Features\Access\UserFederatedIdentity\Application\Usecase\Create\UserFederatedIdentityCreateParams;
use Civi\Lughauth\Features\Access\UserFederatedIdentity\Application\Usecase\Update\UserFederatedIdentityUpdateCheck;
use Civi\Lughauth\Features\Access\UserFederatedIdentity\Application\Usecase\Update\UserFederatedIdentityUpdateParams;
use Civi\Lughauth\Features\Access\UserFederatedIdentity\Domain\UserFederatedIdentity;
use Civi\Lughauth\Features\Access\UserFederatedIdentity\Domain\UserFederatedIdentityAttributes;
use Civi\Lughauth\Features\OAuth\DelegateLogin\Application\Listener\FederatedIdentityWithinOneTenant;

/**
 * A link made through the access API joins a provider and a user of the same tenant: an
 * administrator of one tenant cannot reserve the subject of another tenant's provider.
 */
#[AllowMockObjectsWithoutExpectations]
final class FederatedIdentityWithinOneTenantUnitTest extends TestCase
{
    public function testALinkWithAProviderOfTheUserTenantIsAccepted(): void
    {
        $check = new UserFederatedIdentityCreateCheck(new UserFederatedIdentityCreateParams($this->link('user-a', 'provider-a')));

        $this->assertSame($check, $this->listener()($check));
    }

    public function testALinkWithAProviderOfAnotherTenantIsRejected(): void
    {
        $this->expectRejection();

        $this->listener()(new UserFederatedIdentityCreateCheck(new UserFederatedIdentityCreateParams($this->link('user-a', 'provider-b'))));
    }

    public function testAnUpdateIsCheckedAgainstTheValuesItKeeps(): void
    {
        $current = $this->createMock(UserFederatedIdentity::class);
        $current->method('getUser')->willReturn(new UserRef('user-a'));
        $current->method('getLoginProvider')->willReturn(new TenantLoginProviderRef('provider-a'));
        $this->expectRejection();

        // Only the provider changes, to one of another tenant; the user is the current one.
        $this->listener()(new UserFederatedIdentityUpdateCheck(
            new UserFederatedIdentityUpdateParams((new UserFederatedIdentityAttributes())->loginProvider(new TenantLoginProviderRef('provider-b'))),
            $current
        ));
    }

    private function expectRejection(): void
    {
        $this->expectException(ConstraintException::class);
    }

    private function link(string $user, string $provider): UserFederatedIdentityAttributes
    {
        return (new UserFederatedIdentityAttributes())
            ->user(new UserRef($user))
            ->loginProvider(new TenantLoginProviderRef($provider))
            ->subject('subject-1');
    }

    private function listener(): FederatedIdentityWithinOneTenant
    {
        $tenant = fn (string $uid): TenantRef => new TenantRef($uid);
        $users = $this->createMock(UserQueryRepositoryGateway::class);
        $users->method('findOneByUid')->willReturnCallback(function (string $uid) use ($tenant): ?User {
            $user = $this->createMock(User::class);
            $user->method('getTenant')->willReturn($tenant('tenant-' . substr($uid, -1)));
            return $user;
        });
        $providers = $this->createMock(TenantLoginProviderQueryRepositoryGateway::class);
        $providers->method('findOneByUid')->willReturnCallback(function (string $uid) use ($tenant): ?TenantLoginProvider {
            $provider = $this->createMock(TenantLoginProvider::class);
            $provider->method('getTenant')->willReturn($tenant('tenant-' . substr($uid, -1)));
            return $provider;
        });
        return new FederatedIdentityWithinOneTenant($users, $providers);
    }
}
