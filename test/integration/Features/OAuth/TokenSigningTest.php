<?php

declare(strict_types=1);

namespace Tests\integration\Features\OAuth;

use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Exception\NoActiveKeyException;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\KeyConfig;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\KeyPair;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\TokenType;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\VerificationPolicy;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Infrastructure\Driven\JoseTokenSigner;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Tests\integration\Features\OAuth\Stub\InMemoryTokenStore;

/**
 * Purpose (typ) and key (kid) of the JWTs signed by JoseTokenSigner (tasks 025 and 029).
 */
class TokenSigningTest extends OidcIntegrationTestCase
{
    /** @return array<string, array{TokenType}> */
    public static function purposes(): array
    {
        return array_combine(
            array_map(static fn (TokenType $type): string => $type->name, TokenType::cases()),
            array_map(static fn (TokenType $type): array => [$type], TokenType::cases())
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('purposes')]
    public function testTokenIsAcceptedOnlyByVerifiersOfItsPurpose(TokenType $signed): void
    {
        $signer = $this->signer();
        $token = $signed === TokenType::PreSession
            ? $signer->signKeypass(self::TENANT, ['challenges' => []], new \DateInterval('PT10M'))
            : $signer->sign($signed, ['sub' => self::USER_ID], self::TENANT, new \DateInterval('PT10M'));

        foreach (TokenType::cases() as $expected) {
            $payload = $signer->verify(self::TENANT, $token, VerificationPolicy::expecting($expected));
            if ($expected === $signed) {
                $this->assertNotNull($payload, "{$signed->name} must verify as {$expected->name}");
            } else {
                $this->assertNull($payload, "{$signed->name} must be rejected as {$expected->name}");
            }
        }
    }

    public function testRefreshTokenIsNotAPreSessionEvenThoughItCarriesKeypass(): void
    {
        $refresh = $this->signer()->sign(TokenType::Refresh, ['keypass' => self::USER_ID], self::TENANT, new \DateInterval('PT1H'));

        $this->assertNull($this->signer()->verifiedKeypass(self::TENANT, $refresh));
    }

    public function testPreSessionTokensAreOnlySignedThroughSignKeypass(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->signer()->sign(TokenType::PreSession, ['keypass' => []], self::TENANT, new \DateInterval('PT10M'));
    }

    public function testSigningWithoutActiveKeyFailsWithoutCreatingKeysOrWritingOutput(): void
    {
        $store = new InMemoryTokenStore();
        $signer = new JoseTokenSigner(new KeyConfig(), $store);
        $signer->setLogger(new \Psr\Log\NullLogger());

        $this->expectOutputString('');
        try {
            $signer->sign(TokenType::Access, ['sub' => self::USER_ID], self::TENANT, new \DateInterval('PT10M'));
            $this->fail('Signing without an active key must fail');
        } catch (NoActiveKeyException) {
        }
        $this->assertNull($store->latestExpiration(self::TENANT), 'Signing must never generate keys');
        $this->assertCount(0, $signer->keysAsJwks(self::TENANT)->all());
    }

    public function testRetiredKeyStillVerifiesDuringRetentionWindow(): void
    {
        $retired = $this->storeKey(new \DateTimeImmutable('-9 days'));
        $token = $this->signWith($retired, ['typ' => 'at+jwt'], ['sub' => self::USER_ID]);

        $this->assertNotNull($this->signer()->verify(self::TENANT, $token, VerificationPolicy::expecting(TokenType::Access)));
    }

    public function testKeyBeyondRetentionIsNoLongerPublished(): void
    {
        $expired = $this->storeKey(new \DateTimeImmutable('-40 days'));
        $token = $this->signWith($expired, ['typ' => 'at+jwt'], ['sub' => self::USER_ID]);

        $this->assertArrayNotHasKey($expired->kid, $this->signer()->keysAsJwks(self::TENANT)->all());
        $this->assertNull($this->signer()->verify(self::TENANT, $token, VerificationPolicy::expecting(TokenType::Access)));
    }

    public function testTokenWithoutTypOrKidIsRejected(): void
    {
        $key = $this->tokenStore->currentKey(self::TENANT);
        $untyped = $this->signWith($key, [], ['typ' => 'Bearer', 'sub' => self::USER_ID]);
        $withoutKid = (new JWSBuilder(new AlgorithmManager([new RS256()])))->create()
            ->withPayload(json_encode(['sub' => self::USER_ID, 'nbf' => time(), 'exp' => time() + 600]))
            ->addSignature(JWKFactory::createFromKey($key->privateKey), ['alg' => 'RS256', 'typ' => 'at+jwt'])
            ->build();

        $this->assertNull($this->signer()->verify(self::TENANT, $untyped, VerificationPolicy::expecting(TokenType::Access)));
        $this->assertNull($this->signer()->verify(self::TENANT, (new CompactSerializer())->serialize($withoutKid, 0), VerificationPolicy::expecting(TokenType::Access)));
    }

    private function signer(): JoseTokenSigner
    {
        return $this->container->get(JoseTokenSigner::class);
    }

    /** Stores a seven-day key for the tenant starting at $since, reusing the active key material. */
    private function storeKey(\DateTimeImmutable $since): KeyPair
    {
        $active = $this->tokenStore->currentKey(self::TENANT);
        $pair = new KeyPair('kid-' . $since->getTimestamp(), 'RS256', 'sig', $active->privateKey, $active->publicKey);
        $this->tokenStore->saveKey(self::TENANT, $pair, $since, new \DateInterval('P7D'));
        return $pair;
    }

    /** Signs a token directly, as it was (or would have been) signed with that key. */
    private function signWith(KeyPair $key, array $header, array $claims): string
    {
        $now = time();
        $jws = (new JWSBuilder(new AlgorithmManager([new RS256()])))->create()
            ->withPayload(json_encode([...$claims, 'iat' => $now, 'nbf' => $now, 'exp' => $now + 600]))
            ->addSignature(JWKFactory::createFromKey($key->privateKey), ['alg' => 'RS256', 'kid' => $key->kid, ...$header])
            ->build();
        return (new CompactSerializer())->serialize($jws, 0);
    }
}
