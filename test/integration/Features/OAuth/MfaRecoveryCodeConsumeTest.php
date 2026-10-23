<?php

declare(strict_types=1);

namespace Tests\integration\Features\OAuth;

use PDO;
use Ramsey\Uuid\Uuid;
use Psr\EventDispatcher\EventDispatcherInterface;
use Civi\Lughauth\Features\Access\Tenant\Domain\Tenant;
use Civi\Lughauth\Features\Access\User\Domain\Gateway\UserCommandRepositoryGateway;
use Civi\Lughauth\Features\Access\User\Domain\User;
use Civi\Lughauth\Features\Access\User\Domain\UserRef;
use Civi\Lughauth\Features\Access\UserMfaRecoveryCode\Domain\Gateway\UserMfaRecoveryCodeCommandRepositoryGateway;
use Civi\Lughauth\Features\Access\UserMfaRecoveryCode\Domain\UserMfaRecoveryCode;
use Civi\Lughauth\Features\Access\UserMfaRecoveryCode\Domain\UserMfaRecoveryCodeAttributes;
use Civi\Lughauth\Features\Access\UserMfaRecoveryCode\Domain\ValueObject\UserMfaRecoveryCodeCodeHashVO;
use Civi\Lughauth\Features\Access\UserMfaRecoveryCode\Infrastructure\Connector\Pdo\UserMfaRecoveryCodePdoConnector;
use Civi\Lughauth\Features\Access\UserMfaRecoveryCode\Infrastructure\Driven\UserMfaRecoveryCodeCommandRepositoryAdapter;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception\LoginException;
use Civi\Lughauth\Features\OAuth\Common\Infrastructure\Driven\UserLoaderAdapter;
use Civi\Lughauth\Features\OAuth\Mfa\Domain\Gateway\UserMfaGateway;
use Civi\Lughauth\Features\OAuth\Profile\Infrastructure\Driven\MfaAdapter;
use Civi\Lughauth\Shared\AppConfig;
use Civi\Lughauth\Shared\Infrastructure\EntityChangelog\EntityChangelogService;
use Civi\Lughauth\Shared\Infrastructure\Sql\SqlTemplate;
use Civi\Lughauth\Shared\Security\PasswordHasher;
use Civi\Lughauth\Shared\Security\ReversibleSecretStorage;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * MFA recovery codes consumed against the real table: each one works once, only for its user.
 */
#[AllowMockObjectsWithoutExpectations]
final class MfaRecoveryCodeConsumeTest extends TestCase
{
    private const string CODE = 'A1B2C3D4E5';
    private const string FOREIGN_TENANT = 'foreign-tenant';

    private PDO $pdo;
    private string $owner;
    private string $stranger;

    protected function setUp(): void
    {
        $this->pdo = $this->connect();
        $this->owner = 'mfa-owner-' . bin2hex(random_bytes(6));
        $this->stranger = 'mfa-stranger-' . bin2hex(random_bytes(6));
        $this->repository($this->pdo)->create(UserMfaRecoveryCode::create(
            (new UserMfaRecoveryCodeAttributes())
                ->withDefaults()
                ->uid(Uuid::uuid4()->toString())
                ->user(new UserRef($this->owner))
                ->codeHash(UserMfaRecoveryCodeCodeHashVO::fromPlainText(new PasswordHasher(), self::CODE))
        ));
    }

    protected function tearDown(): void
    {
        $delete = $this->pdo->prepare('DELETE FROM access_user_mfa_recovery_code WHERE user = :user');
        foreach ([$this->owner, $this->stranger] as $user) {
            $delete->execute(['user' => $user]);
        }
    }

    public function testTheCodeWorksOnlyOnce(): void
    {
        $mfa = $this->mfa($this->repository($this->pdo));

        $this->assertSame(
            [true, false],
            [$mfa->consumeRecoveryCode($this->owner, 'tenant', self::CODE), $mfa->consumeRecoveryCode($this->owner, 'tenant', self::CODE)]
        );
    }

    public function testAGeneratedCodeCanBeConsumed(): void
    {
        $mfa = $this->mfa($this->repository($this->pdo));
        $codes = $mfa->generateRecoveryCodes($this->stranger, 'tenant');

        $this->assertTrue($mfa->consumeRecoveryCode($this->stranger, 'tenant', $codes[0]));
        // The generated count query must run on MySQL/MariaDB (it used count("table".*)).
        $this->assertSame(count($codes) - 1, $mfa->countUnusedRecoveryCodes($this->stranger, 'tenant'));
    }

    public function testTheCodeIsAcceptedAsShownInLowerCaseOrGrouped(): void
    {
        $this->assertTrue($this->mfa($this->repository($this->pdo))->consumeRecoveryCode($this->owner, 'tenant', 'a1b2c-3d4e5'));
    }

    public function testAnotherUserCannotUseTheCode(): void
    {
        $mfa = $this->mfa($this->repository($this->pdo));

        $this->assertFalse($mfa->consumeRecoveryCode($this->stranger, 'tenant', self::CODE));
        $this->assertTrue($mfa->consumeRecoveryCode($this->owner, 'tenant', self::CODE), 'A failed attempt of another user must not spend the code');
    }

    public function testTheUserOfAnotherTenantIsNotFound(): void
    {
        $this->expectException(LoginException::class);

        $this->mfa($this->repository($this->pdo))->consumeRecoveryCode($this->owner, self::FOREIGN_TENANT, self::CODE);
    }

    public function testOfTwoConcurrentAttemptsOnlyOneWins(): void
    {
        // The rival, on its own connection, consumes the code after this attempt has read it
        // as unused and before it writes: the worst interleaving of two simultaneous requests.
        $rival = $this->mfa($this->repository($this->connect()));
        $rivalWon = null;
        $real = $this->repository($this->pdo);
        $racing = $this->createMock(UserMfaRecoveryCodeCommandRepositoryGateway::class);
        $racing->method('listForUpdate')->willReturnCallback(
            function (...$args) use ($real, $rival, &$rivalWon) {
                $unused = $real->listForUpdate(...$args);
                $rivalWon = $rival->consumeRecoveryCode($this->owner, 'tenant', self::CODE);
                return $unused;
            }
        );
        $racing->method('update')->willReturnCallback(fn (...$args) => $real->update(...$args));

        $won = $this->mfa($racing)->consumeRecoveryCode($this->owner, 'tenant', self::CODE);

        $this->assertSame([true, false], [$rivalWon, $won]);
    }

    private function mfa(UserMfaRecoveryCodeCommandRepositoryGateway $repository): MfaAdapter
    {
        $users = $this->createMock(UserLoaderAdapter::class);
        $users->method('checkUserOrSubject')->willReturnCallback(
            function (Tenant $_tenant, string $uid): User {
                $user = $this->createMock(User::class);
                $user->method('uid')->willReturn($uid);
                return $user;
            }
        );
        // A user looked up in another tenant is unknown there, as UserLoaderAdapter decides.
        $users->method('checkTenant')->willReturnCallback(function (string $tenant): Tenant {
            if ($tenant === self::FOREIGN_TENANT) {
                throw new LoginException(auth: AuthenticationResult::unknowUser($tenant, 'user'));
            }
            return $this->createMock(Tenant::class);
        });
        return new MfaAdapter(
            $users,
            $this->createMock(UserCommandRepositoryGateway::class),
            $this->createMock(UserMfaGateway::class),
            $repository,
            $this->createMock(ReversibleSecretStorage::class),
            new PasswordHasher(),
        );
    }

    private function repository(PDO $pdo): UserMfaRecoveryCodeCommandRepositoryAdapter
    {
        return new UserMfaRecoveryCodeCommandRepositoryAdapter(
            new UserMfaRecoveryCodePdoConnector(new SqlTemplate($pdo)),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(EntityChangelogService::class),
        );
    }

    private function connect(): PDO
    {
        $config = new AppConfig();
        $dsn = $config->get('database.url');
        if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) {
            $this->markTestSkipped('MySQL integration database not configured');
        }
        try {
            $pdo = new PDO($dsn, $config->get('database.username'), $config->get('database.password'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            // The codes belong to made-up users: only this connection skips the user foreign key.
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            return $pdo;
        } catch (\PDOException) {
            $this->markTestSkipped('MySQL integration database not available');
        }
    }
}
