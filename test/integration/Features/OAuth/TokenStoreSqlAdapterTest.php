<?php

declare(strict_types=1);

namespace Tests\integration\Features\OAuth;

use PDO;
use Ramsey\Uuid\Uuid;
use PHPUnit\Framework\TestCase;
use Civi\Lughauth\Shared\AppConfig;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\KeyPair;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Infrastructure\Driven\TokenStoreSqlAdapter;

/**
 * Per-tenant signing keys in `_oauth_keys_storer` (task 029). Every row it writes uses its own
 * kids and tenants, so the keys already stored in the database are never read or removed.
 */
final class TokenStoreSqlAdapterTest extends TestCase
{
    private const string LEGACY_TENANT = '-';

    private PDO $pdo;
    private TokenStoreSqlAdapter $store;
    private string $tenant;
    /** @var string[] */
    private array $kids = [];

    protected function setUp(): void
    {
        $config = new AppConfig();
        $dsn = $config->get('database.url');
        if (!is_string($dsn) || !str_starts_with($dsn, 'mysql:')) {
            $this->markTestSkipped('MySQL integration database not configured');
        }
        try {
            $this->pdo = new PDO($dsn, $config->get('database.username'), $config->get('database.password'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\PDOException) {
            $this->markTestSkipped('MySQL integration database not available');
        }
        $this->store = new TokenStoreSqlAdapter($this->pdo);
        $this->tenant = 'test-' . Uuid::uuid4()->toString();
    }

    protected function tearDown(): void
    {
        if ($this->kids !== []) {
            $delete = $this->pdo->prepare('DELETE FROM _oauth_keys_storer WHERE keyid = :kid');
            foreach ($this->kids as $kid) {
                $delete->execute(['kid' => $kid]);
            }
        }
    }

    public function testCurrentKeyIsTheNewestActiveKeyOfTheTenant(): void
    {
        $this->save($this->tenant, '-2 days');
        $newest = $this->save($this->tenant, '-1 hour');
        $this->save($this->tenant, '+7 days');
        $this->save('other-' . $this->tenant, '-10 minutes');

        $this->assertSame($newest, $this->store->currentKey($this->tenant)?->kid);
    }

    public function testTenantWithoutOwnKeysSignsWithTheActiveLegacyKeyUntilProvisioned(): void
    {
        $legacy = $this->save(self::LEGACY_TENANT, '-10 minutes');

        $this->assertSame($legacy, $this->store->currentKey($this->tenant)?->kid);
        $this->assertNull($this->store->latestExpiration($this->tenant), 'Legacy keys do not count as provisioned');

        $own = $this->save($this->tenant, '-1 hour');
        $this->assertSame($own, $this->store->currentKey($this->tenant)?->kid);
    }

    public function testLatestExpirationIsTheEndOfTheLastKey(): void
    {
        $this->save($this->tenant, '-1 hour');
        $this->save($this->tenant, '2030-01-01 00:00:00');

        $this->assertEquals(new \DateTimeImmutable('2030-01-08 00:00:00'), $this->store->latestExpiration($this->tenant));
    }

    public function testListKeysPublishesOnlyTheTenantKeysInsideTheWindowPlusLegacySharedOnes(): void
    {
        $future = $this->save($this->tenant, '+7 days');
        $retired = $this->save($this->tenant, '-9 days');
        $tooOld = $this->save($this->tenant, '-40 days');
        $foreign = $this->save('other-' . $this->tenant, '-1 hour');
        $legacy = $this->save(self::LEGACY_TENANT, '-1 hour');

        $kids = array_map(static fn (KeyPair $key): string => $key->kid, $this->store->listKeys($this->tenant, new \DateTimeImmutable('-30 days')));

        $this->assertContains($future, $kids);
        $this->assertContains($retired, $kids);
        $this->assertContains($legacy, $kids);
        $this->assertNotContains($tooOld, $kids);
        $this->assertNotContains($foreign, $kids);
    }

    public function testPurgeRemovesOnlyKeysExpiredBeforeTheInstant(): void
    {
        $this->save($this->tenant, '2000-01-01 00:00:00');
        $kept = $this->save($this->tenant, '2000-01-10 00:00:00');

        $this->store->purgeExpired(new \DateTimeImmutable('2000-01-10 00:00:00'));

        $kids = array_map(static fn (KeyPair $key): string => $key->kid, $this->store->listKeys($this->tenant, new \DateTimeImmutable('1999-01-01')));
        $this->assertSame([$kept], array_values(array_intersect($kids, $this->kids)));
    }

    public function testTenantsToProvisionAreNames(): void
    {
        $this->assertContainsOnlyString($this->store->tenantsToProvision());
    }

    /** Stores a seven-day key starting at $since and returns its kid. */
    private function save(string $tenant, string $since): string
    {
        $kid = Uuid::uuid4()->toString();
        $this->kids[] = $kid;
        $this->store->saveKey($tenant, new KeyPair($kid, 'RS256', 'sig', 'private', 'public'), new \DateTimeImmutable($since), new \DateInterval('P7D'));
        return $kid;
    }
}
