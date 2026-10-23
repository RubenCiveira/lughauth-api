<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Civi\Lughauth\Shared\Exception\ConstraintException;
use Civi\Lughauth\Shared\Security\PasswordHasher;
use Civi\Lughauth\Shared\Security\ReversibleSecretStorage;
use Civi\Lughauth\Features\Access\Tenant\Domain\Tenant;
use Civi\Lughauth\Features\Access\TenantConfig\Domain\Gateway\TenantConfigQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\TenantConfig\Domain\TenantConfig;
use Civi\Lughauth\Features\Access\User\Domain\Gateway\UserCommandRepositoryGateway;
use Civi\Lughauth\Features\Access\User\Domain\User;
use Civi\Lughauth\Features\Access\User\Domain\UserAttributes;
use Civi\Lughauth\Features\Access\User\Domain\UserRef;
use Civi\Lughauth\Features\Access\UserMfaRecoveryCode\Domain\Gateway\UserMfaRecoveryCodeCommandRepositoryGateway;
use Civi\Lughauth\Features\Access\UserMfaRecoveryCode\Domain\Gateway\UserMfaRecoveryCodeSlide;
use Civi\Lughauth\Features\Access\UserMfaRecoveryCode\Domain\UserMfaRecoveryCode;
use Civi\Lughauth\Features\Access\UserMfaRecoveryCode\Domain\UserMfaRecoveryCodeAttributes;
use Civi\Lughauth\Features\Access\UserMfaRecoveryCode\Domain\ValueObject\UserMfaRecoveryCodeCodeHashVO;
use Civi\Lughauth\Features\OAuth\Common\Infrastructure\Driven\UserLoaderAdapter;
use Civi\Lughauth\Features\OAuth\Mfa\Domain\Gateway\UserMfaGateway;
use Civi\Lughauth\Features\OAuth\Profile\Infrastructure\Driven\MfaAdapter;
use Civi\Lughauth\Features\OAuth\User\Domain\Gateway\LoginGateway;

/**
 * Enabling and disabling MFA from the profile. A wrong OTP is a validation error (422), not an
 * internal failure, and the session alone never replaces nor removes an active second factor.
 */
#[AllowMockObjectsWithoutExpectations]
final class MfaAdapterEnableUnitTest extends TestCase
{
    private const string RECOVERY_CODE = 'A1B2C3D4E5';

    private UserMfaGateway&MockObject $mfa;
    private UserCommandRepositoryGateway&MockObject $repository;
    private LoginGateway&MockObject $logins;
    private UserMfaRecoveryCodeCommandRepositoryGateway&MockObject $recovery;
    private bool $tenantForcesMfa = false;
    /** The changes applied after clearing the seed. */
    private ?UserAttributes $clearedWith = null;

    protected function setUp(): void
    {
        $this->mfa = $this->createMock(UserMfaGateway::class);
        $this->repository = $this->createMock(UserCommandRepositoryGateway::class);
        $this->logins = $this->createMock(LoginGateway::class);
        $this->recovery = $this->createMock(UserMfaRecoveryCodeCommandRepositoryGateway::class);
        $this->recovery->method('listForUpdate')->willReturn(new UserMfaRecoveryCodeSlide(null, null, []));
    }

    public function testAWrongOtpIsAValidationErrorAndStoresNothing(): void
    {
        $this->mfa->method('verifyNewOpt')->willReturn(false);
        $this->mfa->expects($this->never())->method('storeSeed');

        $this->assertViolation('wrong_otp', fn () => $this->adapter($this->user(active: false))->enable('user-1', 'tenant1', 'SEED', '000000'));
    }

    public function testARightOtpStoresTheSeed(): void
    {
        $this->mfa->method('verifyNewOpt')->willReturn(true);
        $this->mfa->expects($this->once())->method('storeSeed')->with('tenant1', 'user-name', 'SEED');

        $this->adapter($this->user(active: false))->enable('user-1', 'tenant1', 'SEED', '123456');
    }

    public function testAnActiveSecondFactorIsNotReplacedWithTheSessionAlone(): void
    {
        $this->mfa->method('verifyNewOpt')->willReturn(true);
        $this->mfa->expects($this->never())->method('storeSeed');

        $this->assertViolation('mfa_already_enabled', fn () => $this->adapter($this->user(active: true))->enable('user-1', 'tenant1', 'ATTACKER-SEED', '123456'));
    }

    public function testTheCurrentOtpDisablesMfaAndClearsTheFlag(): void
    {
        $user = $this->user(active: true);
        $this->mfa->expects($this->once())->method('verifyOtp')->with('tenant1', 'user-name', '123456')->willReturn(true);
        $this->expectDisabled($user);

        $this->adapter($user)->disable('user-1', 'tenant1', ' 123456 ');

        $this->assertFalse($this->clearedWith?->isUseSecondFactors());
    }

    public function testARecoveryCodeDisablesMfaAndIsSpent(): void
    {
        $user = $this->user(active: true);
        $this->mfa->method('verifyOtp')->willReturn(false);
        $this->recovery = $this->createMock(UserMfaRecoveryCodeCommandRepositoryGateway::class);
        $this->recovery->method('listForUpdate')->willReturn(new UserMfaRecoveryCodeSlide(null, null, [UserMfaRecoveryCode::create(
            (new UserMfaRecoveryCodeAttributes())->withDefaults()->uid(Uuid::uuid4()->toString())->user(new UserRef('user-1'))
                ->codeHash(UserMfaRecoveryCodeCodeHashVO::fromPlainText(new PasswordHasher(), self::RECOVERY_CODE))
        )]));
        $this->recovery->expects($this->once())->method('update');
        $this->expectDisabled($user);

        $this->adapter($user)->disable('user-1', 'tenant1', self::RECOVERY_CODE);
    }

    public function testAWrongCodeDisablesNothingAndCountsAsAFailedSignIn(): void
    {
        $this->mfa->method('verifyOtp')->willReturn(false);
        $this->repository->expects($this->never())->method('update');
        $this->logins->expects($this->once())->method('markSecondFactorFailed')->with('tenant1', 'user-1');

        $this->assertViolation('wrong_otp', fn () => $this->adapter($this->user(active: true))->disable('user-1', 'tenant1', '000000'));
    }

    public function testATenantThatForcesMfaDoesNotLetItBeDisabled(): void
    {
        $this->tenantForcesMfa = true;
        $this->mfa->expects($this->never())->method('verifyOtp');
        $this->repository->expects($this->never())->method('update');

        $this->assertViolation('mfa_required', fn () => $this->adapter($this->user(active: true))->disable('user-1', 'tenant1', '123456'));
    }

    public function testDisablingWithoutAnActiveSecondFactorDoesNothing(): void
    {
        $this->repository->expects($this->never())->method('update');

        $this->adapter($this->user(active: false))->disable('user-1', 'tenant1', '');
    }

    /** The repository must receive the user with the seed cleared and then the flag off. */
    private function expectDisabled(User&MockObject $user): void
    {
        $withoutSeed = $this->createMock(User::class);
        $user->expects($this->once())->method('setMfaSeed')->with($this->anything(), null)->willReturn($withoutSeed);
        $final = $this->createMock(User::class);
        $withoutSeed->method('update')->willReturnCallback(function (UserAttributes $changes) use ($final): User {
            $this->clearedWith = $changes;
            return $final;
        });
        $this->repository->expects($this->once())->method('update')->with($user, $final);
    }

    private function assertViolation(string $code, Closure $call): void
    {
        try {
            $call();
            $this->fail("Expected the {$code} violation");
        } catch (ConstraintException $ex) {
            $this->assertTrue($ex->includeViolationCode($code));
        }
    }

    private function user(bool $active): User&MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getName')->willReturn('user-name');
        $user->method('uid')->willReturn('user-1');
        $user->method('getSecondFactorSeed')->willReturn($active ? 'sealed-seed' : null);
        $user->method('isUseSecondFactors')->willReturn($active);
        return $user;
    }

    private function adapter(User $user): MfaAdapter
    {
        $users = $this->createMock(UserLoaderAdapter::class);
        $users->method('checkTenant')->willReturn($this->createMock(Tenant::class));
        $users->method('checkUserSubjet')->willReturn($user);
        $users->method('checkUserOrSubject')->willReturn($user);
        $config = $this->createMock(TenantConfig::class);
        $config->method('isForceMfa')->willReturn($this->tenantForcesMfa);
        $configs = $this->createMock(TenantConfigQueryRepositoryGateway::class);
        $configs->method('findOneByTenant')->willReturn($config);
        return new MfaAdapter(
            $users,
            $this->repository,
            $this->mfa,
            $this->recovery,
            $this->createMock(ReversibleSecretStorage::class),
            new PasswordHasher(),
            $configs,
            $this->logins,
        );
    }
}
