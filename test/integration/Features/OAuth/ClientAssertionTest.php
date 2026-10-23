<?php

declare(strict_types=1);

namespace Tests\integration\Features\OAuth;

use Nimbly\Capsule\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Nimbly\Capsule\Factory\RequestFactory;
use Nimbly\Capsule\Factory\StreamFactory;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use PHPUnit\Framework\Attributes\DataProvider;
use Civi\Lughauth\Features\Access\TrustedClient\Domain\Gateway\TrustedClientQueryRepositoryGateway;
use Civi\Lughauth\Features\OAuth\Authentication\Application\AuthorizationRequestResolver;
use Civi\Lughauth\Features\OAuth\Authentication\Application\BackChannelLogoutDispatcher;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationRequest;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driver\Rest\IntrospectionController;
use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driver\Rest\LogoutController;
use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driver\Rest\TokenController;
use Civi\Lughauth\Features\OAuth\Client\Application\ClientAuthenticator;
use Civi\Lughauth\Features\OAuth\Client\Domain\ClientCredentials;
use Civi\Lughauth\Features\OAuth\Client\Domain\ClientData;
use Civi\Lughauth\Features\OAuth\Par\Infrastructure\Driver\Rest\ParController;
use Civi\Lughauth\Features\OAuth\Session\Domain\TemporalAuthCode;
use Civi\Lughauth\Shared\Exception\UnauthorizedException;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\TokenSigner;

/**
 * private_key_jwt client authentication (RFC 7523 §3, task 044) at the token, PAR, introspection
 * and revocation endpoints: a valid assertion authenticates the client once, and a replayed,
 * foreign, misaddressed or expired one, or one of a client registered for another method, does not.
 */
final class ClientAssertionTest extends OidcIntegrationTestCase
{
    private const string CLIENT = 'jwt-client';
    private const string SECRET = 'jwt-client-secret';
    /** A client registered with a secret method, although its keys are known too. */
    private const string SECRET_CLIENT = 'secret-client';

    private static ?JWK $privateKey = null;
    private static ?JWK $foreignKey = null;
    private static string $jwks = '';

    public static function setUpBeforeClass(): void
    {
        [self::$privateKey, $public] = self::keyPair();
        [self::$foreignKey] = self::keyPair();
        self::$jwks = (string) json_encode(['keys' => [$public->all()]]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerClient(self::CLIENT, 'private_key_jwt');
        $this->registerClient(self::SECRET_CLIENT, 'client_secret_post');
        // Every rejection is logged; without a logger it would go to error_log.
        $this->container->get(ClientAuthenticator::class)->setLogger(new NullLogger());
        // Revocation shares LogoutController, which also needs the logout collaborators.
        $this->container->set(TrustedClientQueryRepositoryGateway::class, $this->createStub(TrustedClientQueryRepositoryGateway::class));
        $this->container->set(BackChannelLogoutDispatcher::class, new BackChannelLogoutDispatcher(
            $this->container->get(TokenSigner::class),
            $this->createStub(ClientInterface::class),
            new RequestFactory(),
            new StreamFactory(),
            static function (callable $job): void {
            },
        ));
    }

    /** Each endpoint with the body it needs besides the client credentials and its success status. */
    public static function endpoints(): array
    {
        return [
            'token' => ['token', ['grant_type' => 'client_credentials', 'scope' => 'api:read'], 200],
            'par' => ['par', ['response_type' => 'code', 'redirect_uri' => 'https://app.example.com/callback', 'scope' => 'openid'], 201],
            'introspection' => ['introspect', ['token' => 'not-a-token'], 200],
            'revocation' => ['revoke', ['token' => 'not-a-token'], 200],
        ];
    }

    /** Each endpoint with the body it needs besides the client credentials. */
    public static function bodies(): array
    {
        return array_map(static fn (array $endpoint): array => [$endpoint[0], $endpoint[1]], self::endpoints());
    }

    #[DataProvider('endpoints')]
    public function testValidAssertionAuthenticatesTheClient(string $endpoint, array $body, int $status): void
    {
        $response = $this->call($endpoint, $body + $this->assertionParams());

        $this->assertSame($status, $response->getStatusCode());
    }

    #[DataProvider('endpoints')]
    public function testAssertionIsAcceptedOnce(string $endpoint, array $body, int $status): void
    {
        $params = $body + $this->assertionParams();
        $this->assertSame($status, $this->call($endpoint, $params)->getStatusCode());

        $this->assertInvalidClient($this->call($endpoint, $params));
    }

    #[DataProvider('bodies')]
    public function testAssertionSignedWithAnotherKeyIsRejected(string $endpoint, array $body): void
    {
        $this->assertInvalidClient($this->call($endpoint, $body + $this->assertionParams(key: self::$foreignKey)));
    }

    #[DataProvider('bodies')]
    public function testAssertionAddressedToAnotherTenantIsRejected(string $endpoint, array $body): void
    {
        $other = $this->context->getIssuer('tenant2');
        $this->assertInvalidClient($this->call($endpoint, $body + $this->assertionParams(['aud' => "{$other}/token"])));
    }

    #[DataProvider('bodies')]
    public function testExpiredAssertionIsRejected(string $endpoint, array $body): void
    {
        $this->assertInvalidClient($this->call($endpoint, $body + $this->assertionParams(['exp' => time() - 120])));
    }

    #[DataProvider('bodies')]
    public function testClientNotRegisteredForPrivateKeyJwtCannotUseAnAssertion(string $endpoint, array $body): void
    {
        $claims = ['iss' => self::SECRET_CLIENT, 'sub' => self::SECRET_CLIENT];
        $this->assertInvalidClient($this->call($endpoint, $body + $this->assertionParams($claims)));
    }

    #[DataProvider('bodies')]
    public function testClientRegisteredForPrivateKeyJwtCannotUseItsSecret(string $endpoint, array $body): void
    {
        $this->assertInvalidClient($this->call($endpoint, $body + ['client_id' => self::CLIENT, 'client_secret' => self::SECRET]));
    }

    #[DataProvider('bodies')]
    public function testAssertionCannotBeMixedWithASecret(string $endpoint, array $body): void
    {
        $this->assertInvalidClient($this->call($endpoint, $body + $this->assertionParams() + ['client_secret' => self::SECRET]));
    }

    /**
     * Claims that do not bind the assertion to the client, this server and a fresh, single use.
     */
    public static function rejectedClaims(): array
    {
        return [
            'sub of another client' => [['sub' => self::SECRET_CLIENT]],
            'without sub' => [['sub' => null]],
            'without jti' => [['jti' => null]],
            'without exp' => [['exp' => null]],
            'lifetime beyond one hour' => [['exp' => time() + 7200]],
            'issued in the future' => [['iat' => time() + 600]],
            'aud of another endpoint' => [['aud' => 'https://other.example/token']],
        ];
    }

    #[DataProvider('rejectedClaims')]
    public function testAssertionClaimsAreChecked(array $claims): void
    {
        $this->assertInvalidClient($this->call('token', self::endpoints()['token'][1] + $this->assertionParams($claims)));
    }

    /**
     * A JWT signed by the client serves one use only: a request object cannot authenticate the
     * client afterwards, nor a client assertion be used as a request object, even when it carries
     * the claims of both and is addressed to the issuer, which both accept.
     */
    public function testRequestObjectCannotBeReusedAsClientAssertion(): void
    {
        $both = $this->assertion($this->requestObjectClaims());
        $resolver = $this->container->get(AuthorizationRequestResolver::class);
        $resolver->resolve(self::TENANT, $this->context->getIssuer(self::TENANT), ['client_id' => self::CLIENT, 'request' => $both]);

        $this->assertInvalidClient($this->call('token', self::endpoints()['token'][1] + [
            'client_assertion_type' => ClientCredentials::ASSERTION_TYPE,
            'client_assertion' => $both,
        ]));
    }

    public function testClientAssertionCannotBeReusedAsRequestObject(): void
    {
        $both = $this->assertion(['aud' => $this->context->getIssuer(self::TENANT), 'client_id' => self::CLIENT]);
        $this->assertSame(200, $this->call('token', self::endpoints()['token'][1] + [
            'client_assertion_type' => ClientCredentials::ASSERTION_TYPE,
            'client_assertion' => $both,
        ])->getStatusCode());

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('invalid_request_object');
        $this->container->get(AuthorizationRequestResolver::class)
            ->resolve(self::TENANT, $this->context->getIssuer(self::TENANT), ['client_id' => self::CLIENT, 'request' => $both]);
    }

    /** A JWT with the claims of an authorization request is never a client assertion, even unused. */
    public function testAssertionCarryingAnAuthorizationRequestIsRejected(): void
    {
        $this->assertInvalidClient($this->call('token', self::endpoints()['token'][1] + $this->assertionParams($this->requestObjectClaims())));
    }

    public function testClientIdMustBeTheAssertionIssuer(): void
    {
        $params = self::endpoints()['token'][1] + $this->assertionParams() + ['client_id' => self::SECRET_CLIENT];

        $this->assertInvalidClient($this->call('token', $params));
    }

    public function testAssertionTypeIsRequired(): void
    {
        $params = self::endpoints()['token'][1] + ['client_assertion' => $this->assertion()];

        $this->assertInvalidClient($this->call('token', $params));
    }

    public function testRegisteredSigningAlgorithmIsEnforced(): void
    {
        $this->registerClient(self::CLIENT, 'private_key_jwt', signingAlg: 'ES256');

        $this->assertInvalidClient($this->call('token', self::endpoints()['token'][1] + $this->assertionParams()));
    }

    /**
     * The issuer, the token endpoint and the endpoint called identify this server (RFC 7523 §3,
     * RFC 9126 §2), as a single value or within a list.
     */
    public function testAudienceMayNameTheIssuerTheTokenEndpointOrTheEndpointCalled(): void
    {
        $issuer = $this->context->getIssuer(self::TENANT);
        $par = self::endpoints()['par'][1];

        $this->assertSame(201, $this->call('par', $par + $this->assertionParams(['aud' => $issuer]))->getStatusCode());
        $this->assertSame(201, $this->call('par', $par + $this->assertionParams(['aud' => "{$issuer}/token"]))->getStatusCode());
        $this->assertSame(201, $this->call('par', $par + $this->assertionParams(['aud' => "{$issuer}/par"]))->getStatusCode());
        $this->assertSame(201, $this->call('par', $par + $this->assertionParams(['aud' => ['https://other.example', $issuer]]))->getStatusCode());
        $this->assertInvalidClient($this->call('par', $par + $this->assertionParams(['aud' => "{$issuer}/introspect"])));
    }

    public function testAuthorizationCodeIsRedeemedWithTheRegisteredAssertion(): void
    {
        $client = $this->clientStore->findEnabledClient(self::TENANT, self::CLIENT);
        $code = fn (): string => $this->temporalKeys->registerTemporalAuthCode(new TemporalAuthCode(
            data: new AuthenticationResult(valid: true, id: self::USER_ID, tenant: 'tenant-uid', tenantName: self::TENANT, scope: 'openid'),
            client: $client,
            nonce: 'nonce',
            request: new AuthenticationRequest($client, 'openid', 'https://app.example.com/callback'),
        ));
        $body = fn (string $code): array => [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => 'https://app.example.com/callback',
        ];

        $this->assertInvalidClient($this->call('token', $body($code()) + ['client_id' => self::CLIENT, 'client_secret' => self::SECRET]));
        $this->assertArrayHasKey('id_token', $this->decodeJson($this->call('token', $body($code()) + $this->assertionParams())));
    }

    public function testDeviceGrantAcceptsTheAssertionButNotTheSecret(): void
    {
        $body = ['grant_type' => 'urn:ietf:params:oauth:grant-type:device_code', 'device_code' => 'unknown'];

        $this->assertSame('invalid_client', $this->decodeJson($this->call('token', $body + ['client_id' => self::CLIENT, 'client_secret' => self::SECRET]))['error']);
        // Authenticated: the unknown device code is what fails now.
        $this->assertNotSame('invalid_client', $this->decodeJson($this->call('token', $body + $this->assertionParams()))['error'] ?? null);
    }

    /** Claims of a request object addressed to the issuer, as well as of a client assertion. */
    private function requestObjectClaims(): array
    {
        return [
            'aud' => $this->context->getIssuer(self::TENANT),
            'client_id' => self::CLIENT,
            'response_type' => 'code',
            'redirect_uri' => 'https://app.example.com/callback',
            'scope' => 'openid',
            'code_challenge' => 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
            'code_challenge_method' => 'S256',
        ];
    }

    private function call(string $endpoint, array $body): ResponseInterface
    {
        [$controller, $method] = match ($endpoint) {
            'token' => [TokenController::class, 'post'],
            'par' => [ParController::class, 'post'],
            'introspect' => [IntrospectionController::class, 'post'],
            'revoke' => [LogoutController::class, 'revoke'],
        };
        $request = $this->makeRequest('POST', "/oauth/openid/tenant1/{$endpoint}", $body);
        return $this->container->get($controller)->{$method}($request, new Response(200), ['tenant' => self::TENANT]);
    }

    private function assertInvalidClient(ResponseInterface $response): void
    {
        $body = (string) $response->getBody();
        $this->assertSame([401, 'invalid_client'], [$response->getStatusCode(), json_decode($body, true)['error'] ?? null], $body);
    }

    /** @return array<string, string> */
    private function assertionParams(array $claims = [], ?JWK $key = null): array
    {
        return ['client_assertion_type' => ClientCredentials::ASSERTION_TYPE, 'client_assertion' => $this->assertion($claims, $key)];
    }

    private function assertion(array $claims = [], ?JWK $key = null): string
    {
        $claims = array_filter(array_merge([
            'iss' => self::CLIENT,
            'sub' => self::CLIENT,
            'aud' => $this->context->getIssuer(self::TENANT) . '/token',
            'iat' => time(),
            'exp' => time() + 300,
            'jti' => bin2hex(random_bytes(8)),
        ], $claims), static fn (mixed $value): bool => $value !== null);
        $jws = (new JWSBuilder(new AlgorithmManager([new RS256()])))->create()
            ->withPayload((string) json_encode($claims))
            ->addSignature($key ?? self::$privateKey, ['alg' => 'RS256', 'kid' => 'client-1'])
            ->build();
        return (new CompactSerializer())->serialize($jws, 0);
    }

    private function registerClient(string $id, string $method, string $signingAlg = 'RS256'): void
    {
        $this->clientStore->register($id, $method === 'private_key_jwt' ? self::SECRET : 'other-secret', new ClientData(
            id: $id,
            grants: ['authorization_code', 'client_credentials', 'urn:ietf:params:oauth:grant-type:device_code'],
            secretLogin: true,
            allowedScopesM2m: ['api:read'],
            jwksJson: self::$jwks,
            tokenEndpointAuthMethod: $method,
            tokenEndpointAuthSigningAlg: $signingAlg,
        ));
    }

    /** @return array{JWK, JWK} private and public key */
    private static function keyPair(): array
    {
        $resource = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        openssl_pkey_export($resource, $privatePem);
        $publicPem = (string) (openssl_pkey_get_details($resource)['key'] ?? '');
        return [
            JWKFactory::createFromKey($privatePem, null, ['kid' => 'client-1', 'alg' => 'RS256', 'use' => 'sig']),
            JWKFactory::createFromKey($publicPem, null, ['kid' => 'client-1', 'alg' => 'RS256', 'use' => 'sig']),
        ];
    }
}
