<?php

declare(strict_types=1);

namespace Tests\integration\Features\OAuth;

use PDO;
use Psr\EventDispatcher\EventDispatcherInterface;
use Civi\Lughauth\Features\Access\UserFederatedIdentity\Infrastructure\Connector\Pdo\UserFederatedIdentityPdoConnector;
use Civi\Lughauth\Features\Access\UserFederatedIdentity\Infrastructure\Driven\UserFederatedIdentityCommandRepositoryAdapter;
use Civi\Lughauth\Features\OAuth\DelegateLogin\Infrastructure\Driven\FederatedIdentityAdapter;
use Civi\Lughauth\Shared\AppConfig;
use Civi\Lughauth\Shared\Infrastructure\EntityChangelog\EntityChangelogService;
use Civi\Lughauth\Shared\Infrastructure\Sql\SqlTemplate;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Federated identity links (072) on the user-federated-identity entity, against the real table:
 * per provider record, one account per subject, and a lost race reports the link that won.
 */
#[AllowMockObjectsWithoutExpectations]
final class FederatedIdentityAdapterTest extends TestCase
{
    private PDO $pdo;
    private string $samlA;
    private string $samlB;

    protected function setUp(): void
    {
        $this->pdo = $this->connect();
        $this->samlA = 'saml-a-' . bin2hex(random_bytes(4));
        $this->samlB = 'saml-b-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM access_user_federated_identity WHERE login_provider IN (:a, :b)')->execute(['a' => $this->samlA, 'b' => $this->samlB]);
    }

    public function testALinkBelongsToItsProviderRecordOnly(): void
    {
        $store = $this->store();
        $store->link($this->samlA, 'subject-1', 'user-1');

        $this->assertSame(
            ['user-1', null, true, false, true, false],
            [
                $store->linkedUser($this->samlA, 'subject-1'),
                $store->linkedUser($this->samlB, 'subject-1'),
                $store->isLinkedTo('user-1', $this->samlA),
                $store->isLinkedTo('user-1', $this->samlB),
                $store->hasLinks('user-1'),
                $store->hasLinks('user-2'),
            ]
        );
    }

    public function testASecondLinkOfTheSubjectReportsTheUserThatKeptIt(): void
    {
        $store = $this->store();

        $this->assertSame(
            ['user-1', 'user-1', 'user-1'],
            [
                $store->link($this->samlA, 'subject-1', 'user-1'),
                // A concurrent first sign-in that picked another account loses to the unique index.
                $store->link($this->samlA, 'subject-1', 'user-2'),
                $store->linkedUser($this->samlA, 'subject-1'),
            ]
        );
    }

    private function store(): FederatedIdentityAdapter
    {
        return new FederatedIdentityAdapter(new UserFederatedIdentityCommandRepositoryAdapter(
            new UserFederatedIdentityPdoConnector(new SqlTemplate($this->pdo)),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(EntityChangelogService::class),
        ));
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
        } catch (\PDOException) {
            $this->markTestSkipped('MySQL integration database not available');
        }
        if ($pdo->query("SHOW TABLES LIKE 'access_user_federated_identity'")->rowCount() === 0) {
            $this->markTestSkipped('Table access_user_federated_identity not found: apply the schema migrations');
        }
        // The links point to made-up users and providers: only this connection skips the foreign keys.
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        return $pdo;
    }
}
