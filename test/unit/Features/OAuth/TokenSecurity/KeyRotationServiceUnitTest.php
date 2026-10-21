<?php

declare(strict_types=1);

namespace Tests\unit\Features\OAuth\TokenSecurity;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Application\KeyRotationService;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\KeyPairGenerator;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\KeyConfig;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\KeyPair;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Infrastructure\Driven\OpenSslKeyPairGenerator;
use Tests\integration\Features\OAuth\Stub\InMemoryTokenStore;

final class KeyRotationServiceUnitTest extends TestCase
{
    private InMemoryTokenStore $store;
    private Psr16Cache $cache;
    private int $generated = 0;
    private bool $failNext = false;
    private int $failAfter = PHP_INT_MAX;
    /** @var list<callable> */
    private array $deferred = [];

    protected function setUp(): void
    {
        $this->store = new InMemoryTokenStore();
        $this->cache = new Psr16Cache(new ArrayAdapter());
    }

    public function testFirstRotationProvisionsTheActiveKeyPlusFutureKeys(): void
    {
        $config = new KeyConfig();

        $created = $this->service()->rotate('acme');

        $this->assertSame($config->futures + 1, $created);
        $this->assertNotNull($this->store->currentKey('acme'));
        $this->assertGreaterThanOrEqual(
            (new \DateTimeImmutable())->add(new \DateInterval('P' . 7 * $config->futures . 'D')),
            $this->store->latestExpiration('acme')
        );
    }

    public function testRotationOnlyAddsMissingKeys(): void
    {
        $service = $this->service();
        $service->rotate('acme');

        $this->assertSame(0, $service->rotate('acme'));
    }

    public function testRotationContinuesTheChainFromTheLastKey(): void
    {
        $lastEnd = new \DateTimeImmutable('+3 days');
        $this->store->saveKey('acme', $this->key(), $lastEnd->sub(new \DateInterval('P7D')), new \DateInterval('P7D'));

        $this->service()->rotate('acme');

        $next = array_filter(
            $this->store->listKeys('acme', $lastEnd),
            fn (KeyPair $key): bool => $key->kid !== 'kid-0'
        );
        $this->assertNotEmpty($next);
        $this->assertSame('kid-0', $this->store->currentKey('acme')?->kid, 'The active key keeps signing until it expires');
    }

    public function testRotationInvalidatesOnlyTheRotatedTenantJwks(): void
    {
        $this->cache->set(KeyRotationService::jwksCacheKey('acme'), '{"keys":[]}');
        $this->cache->set(KeyRotationService::jwksCacheKey('globex'), '{"keys":[]}');

        $this->service()->rotate('acme');

        $this->assertFalse($this->cache->has(KeyRotationService::jwksCacheKey('acme')));
        $this->assertTrue($this->cache->has(KeyRotationService::jwksCacheKey('globex')));
    }

    public function testRotateAllSurvivesAFailingTenantAndPurgesKeysPastRetention(): void
    {
        $this->store->saveKey('acme', $this->key(), new \DateTimeImmutable('-60 days'), new \DateInterval('P7D'));
        $this->store->saveKey('globex', $this->key(), new \DateTimeImmutable('-60 days'), new \DateInterval('P7D'));
        $this->failNext = true;

        $this->service()->rotateAll();

        $this->assertNull($this->store->currentKey('acme'), 'The failing tenant is retried on the next run');
        $this->assertNotNull($this->store->currentKey('globex'));
        $this->assertSame([], $this->store->listKeys('acme', new \DateTimeImmutable('-1 year')), 'Keys past retention are purged');
    }

    public function testKeysStoredBeforeAGenerationFailureArePublished(): void
    {
        $this->cache->set(KeyRotationService::jwksCacheKey('acme'), '{"keys":[]}');
        $this->failAfter = 2;

        try {
            $this->service()->rotate('acme');
            $this->fail('The generation failure must propagate');
        } catch (\RuntimeException) {
        }

        $this->assertNotNull($this->store->currentKey('acme'));
        $this->assertFalse($this->cache->has(KeyRotationService::jwksCacheKey('acme')), 'The new keys must reach the JWKS');
    }

    public function testFallbackWithoutActiveKeyCreatesOnlyThatKeyAndDefersTheChain(): void
    {
        $service = $this->service();

        $service->provisionActiveKey('acme');

        $this->assertSame(1, $this->generated, 'Only one RSA key is generated while serving the request');
        $this->assertNotNull($this->store->currentKey('acme'));
        $this->assertCount(1, $this->deferred);
        ($this->deferred[0])();
        $this->assertSame((new KeyConfig())->futures + 1, $this->generated);
    }

    public function testFallbackDoesNotReplaceAnActiveKeyCreatedMeanwhile(): void
    {
        $this->store->saveKey('acme', $this->key(), new \DateTimeImmutable('-1 hour'), new \DateInterval('P7D'));

        $this->service()->provisionActiveKey('acme');

        $this->assertSame(1, $this->generated);
        $this->assertSame('kid-0', $this->store->currentKey('acme')?->kid);
    }

    public function testFutureKeysAreDeferredOnlyWhenTheChainIsShort(): void
    {
        $covered = $this->service();
        $covered->rotate('acme');
        $covered->ensureFutureKeys('acme');
        $this->assertSame([], $this->deferred, 'A full chain needs nothing');

        $this->store->saveKey('globex', $this->key(), new \DateTimeImmutable('-1 hour'), new \DateInterval('P7D'));
        $short = $this->service();
        $short->ensureFutureKeys('globex');
        $short->ensureFutureKeys('globex');
        $this->assertCount(1, $this->deferred, 'One deferred rotation per tenant and request');

        ($this->deferred[0])();
        $this->assertGreaterThanOrEqual(new \DateTimeImmutable('+21 days'), $this->store->latestExpiration('globex'));
    }

    public function testOpenSslGeneratorProducesUsableRs256KeyPairs(): void
    {
        $pair = (new OpenSslKeyPairGenerator())->generate();

        $this->assertSame('RS256', $pair->alg);
        $this->assertNotSame('', $pair->kid);
        $this->assertTrue(openssl_sign('data', $signature, $pair->privateKey, OPENSSL_ALGO_SHA256));
        $this->assertSame(1, openssl_verify('data', $signature, $pair->publicKey, OPENSSL_ALGO_SHA256));
    }

    private function service(): KeyRotationService
    {
        $generator = new class ($this) implements KeyPairGenerator {
            public function __construct(private readonly KeyRotationServiceUnitTest $test)
            {
            }
            public function generate(): KeyPair
            {
                return $this->test->key();
            }
        };
        $service = new KeyRotationService(
            new KeyConfig(),
            $this->store,
            $generator,
            $this->cache,
            new LockFactory(new InMemoryStore()),
            function (callable $job): void {
                $this->deferred[] = $job;
            }
        );
        $service->setLogger(new NullLogger());
        return $service;
    }

    /** Key pair with a predictable kid; key material is irrelevant to rotation. */
    public function key(): KeyPair
    {
        if ($this->failNext || $this->generated >= $this->failAfter) {
            $this->failNext = false;
            throw new \RuntimeException('generation failed');
        }
        return new KeyPair('kid-' . $this->generated++, 'RS256', 'sig', 'private', 'public');
    }
}
