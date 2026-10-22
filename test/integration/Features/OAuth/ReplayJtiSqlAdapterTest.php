<?php

declare(strict_types=1);

namespace Tests\integration\Features\OAuth;

use PDO;
use Civi\Lughauth\Features\OAuth\Par\Domain\ParRequest;
use Civi\Lughauth\Features\OAuth\Par\Infrastructure\Driven\ParRequestSqlAdapter;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\ReplayPurpose;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Infrastructure\Driven\ReplayJtiSqlAdapter;
use Civi\Lughauth\Shared\AppConfig;
use PHPUnit\Framework\TestCase;

/**
 * One-time use enforced by the database (EP-07): request object JTIs and PAR request_uris.
 */
final class ReplayJtiSqlAdapterTest extends TestCase
{
    private const string TENANT = 'tenant-replay-test';

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = $this->connect();
    }

    protected function tearDown(): void
    {
        $this->pdo->prepare('DELETE FROM _oauth_replay_jti WHERE tenant_id = :tenant')->execute(['tenant' => self::TENANT]);
        $this->pdo->prepare('DELETE FROM _oauth_par_request WHERE tenant_id = :tenant')->execute(['tenant' => self::TENANT]);
    }

    public function testJtiIsConsumedOncePerClientAndPurpose(): void
    {
        $store = new ReplayJtiSqlAdapter($this->pdo);
        $expires = new \DateTimeImmutable('+5 minutes');

        $this->assertTrue($store->consume(self::TENANT, 'client-a', ReplayPurpose::RequestObject, 'jti-1', $expires));
        $this->assertFalse($store->consume(self::TENANT, 'client-a', ReplayPurpose::RequestObject, 'jti-1', $expires));
        $this->assertTrue($store->consume(self::TENANT, 'client-b', ReplayPurpose::RequestObject, 'jti-1', $expires));
        $this->assertTrue($store->consume(self::TENANT, 'client-a', ReplayPurpose::ClientAssertion, 'jti-1', $expires));
    }

    public function testReleasedJtiCanBeConsumedAgain(): void
    {
        $store = new ReplayJtiSqlAdapter($this->pdo);
        $expires = new \DateTimeImmutable('+5 minutes');
        $store->consume(self::TENANT, 'client-a', ReplayPurpose::AuthorizationRequest, 'ref-1', $expires);
        $store->consume(self::TENANT, 'client-a', ReplayPurpose::RequestObject, 'ref-1', $expires);

        $store->release(self::TENANT, 'client-a', ReplayPurpose::AuthorizationRequest, 'ref-1');

        $this->assertTrue($store->consume(self::TENANT, 'client-a', ReplayPurpose::AuthorizationRequest, 'ref-1', $expires));
        $this->assertFalse($store->consume(self::TENANT, 'client-a', ReplayPurpose::RequestObject, 'ref-1', $expires), 'Only the released purpose is reopened');
    }

    public function testPurgeRemovesOnlyExpiredJtis(): void
    {
        $store = new ReplayJtiSqlAdapter($this->pdo);
        $store->consume(self::TENANT, 'client-a', ReplayPurpose::RequestObject, 'old', new \DateTimeImmutable('-1 minute'));
        $store->consume(self::TENANT, 'client-a', ReplayPurpose::RequestObject, 'live', new \DateTimeImmutable('+5 minutes'));

        $store->purgeExpired();

        $this->assertTrue($store->consume(self::TENANT, 'client-a', ReplayPurpose::RequestObject, 'old', new \DateTimeImmutable('+5 minutes')));
        $this->assertFalse($store->consume(self::TENANT, 'client-a', ReplayPurpose::RequestObject, 'live', new \DateTimeImmutable('+5 minutes')));
    }

    public function testPushedRequestIsConsumedOnlyOnceAndOnlyInItsTenant(): void
    {
        $store = new ParRequestSqlAdapter($this->pdo);
        $request = ParRequest::create(self::TENANT, 'client-a', ['response_type' => 'code']);
        $store->store($request);

        $this->assertFalse($store->consume($request->requestUri, 'other-tenant'));
        $this->assertTrue($store->consume($request->requestUri, self::TENANT));
        $this->assertFalse($store->consume($request->requestUri, self::TENANT));
        $this->assertTrue($store->findByUri($request->requestUri, self::TENANT)?->isUsed());
    }

    public function testExpiredPushedRequestIsNotConsumed(): void
    {
        $store = new ParRequestSqlAdapter($this->pdo);
        $expired = new ParRequest('urn:ietf:params:oauth:request_uri:' . bin2hex(random_bytes(16)), self::TENANT, 'client-a', [], new \DateTimeImmutable('+1 minute'));
        $store->store($expired);
        $this->pdo->prepare('UPDATE _oauth_par_request SET expires_at = :past WHERE request_uri = :uri')
            ->execute(['past' => (new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s'), 'uri' => $expired->requestUri]);

        $this->assertFalse($store->consume($expired->requestUri, self::TENANT));

        $store->purgeExpired();
        $this->assertNull($store->findByUri($expired->requestUri, self::TENANT));
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
        if ($pdo->query("SHOW TABLES LIKE '_oauth_replay_jti'")->rowCount() === 0) {
            $this->markTestSkipped('Table _oauth_replay_jti not found: apply the schema migrations');
        }
        return $pdo;
    }
}
