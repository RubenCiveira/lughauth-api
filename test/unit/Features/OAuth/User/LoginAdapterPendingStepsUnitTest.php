<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Civi\Lughauth\Features\Access\RelyingParty\Domain\Gateway\RelyingPartyQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\Role\Domain\Gateway\RoleQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\Tenant\Domain\Tenant;
use Civi\Lughauth\Features\Access\TenantConfig\Domain\Gateway\TenantConfigQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\User\Domain\Gateway\UserCommandRepositoryGateway;
use Civi\Lughauth\Features\Access\User\Domain\User;
use Civi\Lughauth\Features\Access\UserAcceptedTermsOfUse\Domain\Gateway\UserAcceptedTermsOfUseQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\UserGroupMembership\Domain\Gateway\UserGroupMembershipQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\UserRoleAssignment\Domain\Gateway\UserRoleAssignmentQueryRepositoryGateway;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationRequest;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\IdentificationMethod;
use Civi\Lughauth\Features\OAuth\Client\Domain\ClientData;
use Civi\Lughauth\Features\OAuth\Client\Infrastructure\Driven\TrustedClientFinder;
use Civi\Lughauth\Features\OAuth\Common\Infrastructure\Driven\UserLoaderAdapter;
use Civi\Lughauth\Features\OAuth\Consent\Application\Usecase\ScopesConsentUsecase;
use Civi\Lughauth\Features\OAuth\User\Infrastructure\Driven\LoginAdapter;
use Civi\Lughauth\Shared\Security\PasswordHasher;

/**
 * After a password login, the steps it asked for (temporal password change, MFA) cannot be
 * skipped by posting a later step such as the consent directly.
 */
#[AllowMockObjectsWithoutExpectations]
final class LoginAdapterPendingStepsUnitTest extends TestCase
{
    public function testMfaIsStillRequiredWhenALaterStepContinuesAPasswordLogin(): void
    {
        $result = $this->fill($this->user(temporal: false), $this->byPassword());

        $this->assertSame(AuthenticationResult::ERR_MFA_REQUIRED, $result->error);
    }

    public function testATemporalPasswordIsStillChangedAfterTheMfa(): void
    {
        $result = $this->fill($this->user(temporal: true), $this->byPassword()->withMfa(true, 'otp'));

        $this->assertSame(AuthenticationResult::ERR_NEW_PASSWORD_REQUIRED, $result->error);
    }

    public function testEnrolmentIsPendingOnlyWithoutASeed(): void
    {
        $withSeed = $this->user(temporal: false);
        $withoutSeed = $this->createMock(User::class);
        $withoutSeed->method('getSecondFactorSeed')->willReturn(null);
        $withoutSeed->method('isUseSecondFactors')->willReturn(true);

        $this->assertSame(
            [false, true],
            [$this->adapter($withSeed)->mfaEnrolmentPending('tenant1', 'user-1'), $this->adapter($withoutSeed)->mfaEnrolmentPending('tenant1', 'user-1')]
        );
    }

    private function byPassword(): ChallengesState
    {
        return (new ChallengesState(username: 'user-1'))->identifiedBy(IdentificationMethod::PASSWORD);
    }

    private function user(bool $temporal): User
    {
        $user = $this->createMock(User::class);
        $user->method('getSecondFactorSeed')->willReturn('sealed-seed');
        $user->method('isUseSecondFactors')->willReturn(true);
        $user->method('isTemporalPassword')->willReturn($temporal);
        return $user;
    }

    private function fill(User $user, ChallengesState $challenges): AuthenticationResult
    {
        return $this->adapter($user)->fillPreAuthenticated(
            'tenant1',
            new AuthenticationRequest(
                client: new ClientData('client-123', ['code'], true),
                scope: 'openid',
                redirect: 'https://client.example/callback',
                responseType: 'code',
                audiences: ['client-123']
            ),
            $challenges
        );
    }

    private function adapter(User $user): LoginAdapter
    {
        $users = $this->createMock(UserLoaderAdapter::class);
        $users->method('checkTenant')->willReturn($this->createMock(Tenant::class));
        $users->method('checkUserOrSubject')->willReturn($user);
        return new LoginAdapter(
            new PasswordHasher(),
            $users,
            $this->createMock(TenantConfigQueryRepositoryGateway::class),
            $this->createMock(UserAcceptedTermsOfUseQueryRepositoryGateway::class),
            $this->createMock(UserGroupMembershipQueryRepositoryGateway::class),
            $this->createMock(UserRoleAssignmentQueryRepositoryGateway::class),
            $this->createMock(TrustedClientFinder::class),
            $this->createMock(RelyingPartyQueryRepositoryGateway::class),
            $this->createMock(UserCommandRepositoryGateway::class),
            $this->createMock(RoleQueryRepositoryGateway::class),
            $this->createMock(ScopesConsentUsecase::class),
        );
    }
}
