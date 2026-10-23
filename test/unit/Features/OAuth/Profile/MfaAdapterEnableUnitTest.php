<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Civi\Lughauth\Shared\Exception\ConstraintException;
use Civi\Lughauth\Shared\Security\PasswordHasher;
use Civi\Lughauth\Shared\Security\ReversibleSecretStorage;
use Civi\Lughauth\Features\Access\Tenant\Domain\Tenant;
use Civi\Lughauth\Features\Access\User\Domain\Gateway\UserCommandRepositoryGateway;
use Civi\Lughauth\Features\Access\User\Domain\User;
use Civi\Lughauth\Features\Access\UserMfaRecoveryCode\Domain\Gateway\UserMfaRecoveryCodeCommandRepositoryGateway;
use Civi\Lughauth\Features\OAuth\Common\Infrastructure\Driven\UserLoaderAdapter;
use Civi\Lughauth\Features\OAuth\Mfa\Domain\Gateway\UserMfaGateway;
use Civi\Lughauth\Features\OAuth\Profile\Infrastructure\Driven\MfaAdapter;

/**
 * Enabling MFA from the profile stores the seed only after a right OTP; a wrong one is a
 * validation error (422), not an internal failure.
 */
#[AllowMockObjectsWithoutExpectations]
final class MfaAdapterEnableUnitTest extends TestCase
{
    public function testAWrongOtpIsAValidationErrorAndStoresNothing(): void
    {
        $mfa = $this->createMock(UserMfaGateway::class);
        $mfa->method('verifyNewOpt')->willReturn(false);
        $mfa->expects($this->never())->method('storeSeed');

        try {
            $this->adapter($mfa)->enable('user-1', 'tenant1', 'SEED', '000000');
            $this->fail('A wrong OTP must not enable MFA');
        } catch (ConstraintException $ex) {
            $this->assertTrue($ex->includeViolationCode('wrong_otp'));
        }
    }

    public function testARightOtpStoresTheSeed(): void
    {
        $mfa = $this->createMock(UserMfaGateway::class);
        $mfa->method('verifyNewOpt')->willReturn(true);
        $mfa->expects($this->once())->method('storeSeed')->with('tenant1', 'user-name', 'SEED');

        $this->adapter($mfa)->enable('user-1', 'tenant1', 'SEED', '123456');
    }

    private function adapter(UserMfaGateway $mfa): MfaAdapter
    {
        $user = $this->createMock(User::class);
        $user->method('getName')->willReturn('user-name');
        $users = $this->createMock(UserLoaderAdapter::class);
        $users->method('checkTenant')->willReturn($this->createMock(Tenant::class));
        $users->method('checkUserSubjet')->willReturn($user);
        return new MfaAdapter(
            $users,
            $this->createMock(UserCommandRepositoryGateway::class),
            $mfa,
            $this->createMock(UserMfaRecoveryCodeCommandRepositoryGateway::class),
            $this->createMock(ReversibleSecretStorage::class),
            new PasswordHasher(),
        );
    }
}
