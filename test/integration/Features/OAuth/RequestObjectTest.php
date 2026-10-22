<?php

declare(strict_types=1);

namespace Tests\integration\Features\OAuth;

use Nimbly\Capsule\Response;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Civi\Lughauth\Shared\Exception\UnauthorizedException;
use Civi\Lughauth\Features\OAuth\Authentication\Application\AuthorizationRequestResolver;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ResolvedAuthorizationRequest;
use Civi\Lughauth\Features\OAuth\Client\Domain\ClientData;
use Civi\Lughauth\Features\OAuth\Par\Infrastructure\Driver\Rest\ParController;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\ReplayPurpose;

/**
 * Signed request objects (JAR, RFC 9101) at /authorize: only verified, fresh, single-use claims
 * addressed to this tenant are used (task 047).
 */
final class RequestObjectTest extends OidcIntegrationTestCase
{
    private const string CLIENT = 'jar-client';
    private const string SECRET = 'jar-client-secret';

    private static ?JWK $privateKey = null;
    private static string $jwks = '';

    private AuthorizationRequestResolver $resolver;

    public static function setUpBeforeClass(): void
    {
        $resource = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        openssl_pkey_export($resource, $privatePem);
        $publicPem = (string) (openssl_pkey_get_details($resource)['key'] ?? '');
        self::$privateKey = JWKFactory::createFromKey($privatePem, null, ['kid' => 'jar-1', 'alg' => 'RS256', 'use' => 'sig']);
        $public = JWKFactory::createFromKey($publicPem, null, ['kid' => 'jar-1', 'alg' => 'RS256', 'use' => 'sig']);
        self::$jwks = (string) json_encode(['keys' => [$public->all()]]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = $this->container->get(AuthorizationRequestResolver::class);
        $this->registerClient(jwksJson: self::$jwks);
    }

    public function testValidRequestObjectIsUsedInsteadOfTheQuery(): void
    {
        $resolved = $this->resolve([
            'client_id' => self::CLIENT,
            'request' => $this->requestObject(),
            'redirect_uri' => 'https://evil.example/callback',
            'scope' => 'openid admin',
        ]);

        $this->assertSame(ResolvedAuthorizationRequest::ORIGIN_SIGNED, $resolved->origin);
        $this->assertTrue($resolved->consumedNow);
        $this->assertSame('https://app.example.com/callback', $resolved->params['redirect_uri']);
        $this->assertSame('openid', $resolved->params['scope']);
        $this->assertArrayNotHasKey('jti', $resolved->params);
        $this->assertArrayNotHasKey('aud', $resolved->params);
    }

    public function testExpiredRequestObjectIsRejected(): void
    {
        $this->assertRejected(['client_id' => self::CLIENT, 'request' => $this->requestObject(['exp' => time() - 300])]);
    }

    public function testReplayedRequestObjectIsRejected(): void
    {
        $query = ['client_id' => self::CLIENT, 'request' => $this->requestObject()];
        $this->resolve($query);

        $this->assertRejected($query);
    }

    public function testRequestObjectAddressedToAnotherTenantIsRejected(): void
    {
        $this->assertRejected(['client_id' => self::CLIENT, 'request' => $this->requestObject(['aud' => $this->context->getIssuer('tenant2')])]);
    }

    public function testJwksUriInPrivateNetworkIsRejected(): void
    {
        $this->registerClient(jwksUri: 'https://10.0.0.5/jwks.json');

        $this->assertRejected(['client_id' => self::CLIENT, 'request' => $this->requestObject()]);
    }

    public function testJwksUriOverPlainHttpIsRejected(): void
    {
        $this->registerClient(jwksUri: 'http://client.example/jwks.json');

        $this->assertRejected(['client_id' => self::CLIENT, 'request' => $this->requestObject()]);
    }

    public function testRejectedDirectRequestDoesNotSpendTheRequestObject(): void
    {
        $this->parPolicy->requirePar(self::TENANT);
        $requestObject = $this->requestObject(['jti' => 'kept-jti']);

        try {
            $this->resolve(['client_id' => self::CLIENT, 'request' => $requestObject]);
            $this->fail('A tenant requiring PAR rejects request objects sent to /authorize');
        } catch (UnauthorizedException $ex) {
            $this->assertSame('invalid_request', $ex->getMessage());
        }
        $this->assertTrue(
            $this->replayJtis->consume(self::TENANT, self::CLIENT, ReplayPurpose::RequestObject, 'kept-jti', new \DateTimeImmutable('+5 minutes')),
            'The rejected request object must still be unused'
        );
    }

    public function testRequestObjectInsideAPushedRequestIsVerified(): void
    {
        $push = fn (string $requestObject): \Psr\Http\Message\ResponseInterface => $this->container->get(ParController::class)->post(
            $this->makeRequest('POST', '/oauth/openid/tenant1/par', [
                'client_id' => self::CLIENT,
                'client_secret' => self::SECRET,
                'response_type' => 'code',
                'redirect_uri' => 'https://ignored.example/callback',
                'request' => $requestObject,
            ]),
            new Response(200),
            ['tenant' => self::TENANT]
        );

        $valid = $this->resolve(['client_id' => self::CLIENT, 'request_uri' => $this->assertJsonField($push($this->requestObject()), 'request_uri')]);
        $this->assertSame(ResolvedAuthorizationRequest::ORIGIN_PUSHED, $valid->origin);
        $this->assertSame('https://app.example.com/callback', $valid->params['redirect_uri']);

        $expired = $this->assertJsonField($push($this->requestObject(['exp' => time() - 300])), 'request_uri');
        $this->assertRejected(['client_id' => self::CLIENT, 'request_uri' => $expired]);
    }

    private function registerClient(?string $jwksJson = null, ?string $jwksUri = null): void
    {
        $this->clientStore->register(self::CLIENT, self::SECRET, new ClientData(
            id: self::CLIENT,
            grants: ['authorization_code'],
            secretLogin: true,
            requestObjectSigningAlg: 'RS256',
            jwksUri: $jwksUri,
            jwksJson: $jwksJson,
            tokenEndpointAuthMethod: 'client_secret_post',
        ));
    }

    private function requestObject(array $overrides = []): string
    {
        $claims = array_merge([
            'iss' => self::CLIENT,
            'aud' => $this->context->getIssuer(self::TENANT),
            'client_id' => self::CLIENT,
            'iat' => time(),
            'exp' => time() + 300,
            'jti' => bin2hex(random_bytes(8)),
            'response_type' => 'code',
            'redirect_uri' => 'https://app.example.com/callback',
            'scope' => 'openid',
            'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            'code_challenge_method' => 'S256',
        ], $overrides);
        $jws = (new JWSBuilder(new AlgorithmManager([new RS256()])))->create()
            ->withPayload((string) json_encode($claims))
            ->addSignature(self::$privateKey, ['alg' => 'RS256', 'kid' => 'jar-1'])
            ->build();
        return (new CompactSerializer())->serialize($jws, 0);
    }

    private function resolve(array $query): ResolvedAuthorizationRequest
    {
        return $this->resolver->resolve(self::TENANT, $this->context->getIssuer(self::TENANT), $query);
    }

    private function assertRejected(array $query): void
    {
        try {
            $this->resolve($query);
            $this->fail('The request object must be rejected');
        } catch (UnauthorizedException $ex) {
            $this->assertSame('invalid_request_object', $ex->getMessage());
        }
    }
}
