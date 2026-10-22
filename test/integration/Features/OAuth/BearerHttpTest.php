<?php

declare(strict_types=1);

namespace Tests\integration\Features\OAuth;

use Nimbly\Capsule\Factory\ServerRequestFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Civi\Lughauth\Shared\AppConfig;
use Civi\Lughauth\Bootstrap\Middleware\JwtVerifierMiddleware;
use Civi\Lughauth\Bootstrap\Plugin\ErrorsPlugin;
use Civi\Lughauth\Bootstrap\Plugin\OAuthPlugin;
use Civi\Lughauth\Features\Access\TrustedClient\Domain\Gateway\TrustedClientQueryRepositoryGateway;
use Civi\Lughauth\Features\OAuth\Profile\Domain\Gateway\ProfileGateway;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\TokenSigner;

/**
 * EP-06 end to end over HTTP: the real routes of OAuthPlugin behind the routing middleware,
 * JwtVerifierMiddleware and the ErrorsPlugin handlers, wired in the same order as Micro. A
 * resource route (`/api/whoami`) stands for the APIs the middleware protects with the JWKS.
 */
class BearerHttpTest extends OidcIntegrationTestCase
{
    private const string RESOURCE = 'api-resource';

    private App $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container->set(ClientInterface::class, $this->createStub(ClientInterface::class));
        $this->container->set(RequestFactoryInterface::class, $this->createStub(RequestFactoryInterface::class));
        $this->container->set(StreamFactoryInterface::class, $this->createStub(StreamFactoryInterface::class));
        $this->container->set(ProfileGateway::class, $this->createStub(ProfileGateway::class));
        $this->container->set(TrustedClientQueryRepositoryGateway::class, $this->createStub(TrustedClientQueryRepositoryGateway::class));
        // The JWKS the resource routes trust: the published keys of the tenant.
        $this->cache->set('jwks.verify.publickey', json_encode($this->container->get(TokenSigner::class)->keysAsJwks(self::TENANT)));

        $this->app = AppFactory::createFromContainer($this->container);
        (new OAuthPlugin())->registerRoutes($this->app);
        // Slim binds route closures to the container: capture the context explicitly.
        $context = $this->context;
        $this->app->get('/api/whoami', function (ServerRequestInterface $request, ResponseInterface $response) use ($context): ResponseInterface {
            $identity = $context->getIdentity();
            $response->getBody()->write((string) json_encode(['sub' => $identity->getId(), 'tenant' => $identity->getTenant()]));
            return $response;
        });
        $this->app->add($this->container->get(JwtVerifierMiddleware::class));
        $this->app->addRoutingMiddleware();
        (new ErrorsPlugin())->registerErrorHandler($this->app->addErrorMiddleware(false, false, false));
    }

    protected function createAppConfig(): AppConfig
    {
        $base = parent::createAppConfig();
        $config = $this->createStub(AppConfig::class);
        $config->method('get')->willReturnCallback(fn (string $key, mixed $default = null): mixed => match ($key) {
            'security.jwt.verify.publickey.location' => 'http://jwks.invalid',
            'security.jwt.verify.audiences' => self::RESOURCE,
            default => $base->get($key, $default),
        });
        return $config;
    }

    public function testUserInfoAcceptsAnAccessTokenOfItsTenant(): void
    {
        $response = $this->send('GET', '/oauth/openid/tenant1/userinfo', bearer: $this->tokens()['access_token']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(self::USER_ID, $this->decodeJson($response)['sub']);
    }

    public function testUserInfoOfAnotherTenantRejectsTheToken(): void
    {
        $token = $this->tokens()['access_token'];

        // In the header the middleware rejects it; in the body, UserInfo itself.
        $this->assertInvalidToken($this->send('GET', '/oauth/openid/tenant2/userinfo', bearer: $token));
        $this->assertInvalidToken($this->send('POST', '/oauth/openid/tenant2/userinfo', ['access_token' => $token]));
    }

    public function testUserInfoRejectsAnIdToken(): void
    {
        $this->assertInvalidToken($this->send('POST', '/oauth/openid/tenant1/userinfo', bearer: $this->tokens()['id_token']));
    }

    public function testResourceApiRequiresItsAudience(): void
    {
        $forClient = $this->send('GET', '/api/whoami', bearer: $this->tokens()['access_token']);
        $forResource = $this->send('GET', '/api/whoami', bearer: $this->tokens(self::RESOURCE)['access_token']);

        $this->assertInvalidToken($forClient);
        $this->assertSame(200, $forResource->getStatusCode());
        $this->assertSame(['sub' => self::USER_ID, 'tenant' => self::TENANT], $this->decodeJson($forResource));
    }

    public function testResourceApiVerifiesALowerCaseBearerScheme(): void
    {
        $response = $this->send('GET', '/api/whoami', headers: ['Authorization' => 'bearer not-a-jwt']);

        $this->assertInvalidToken($response);
    }

    public function testRevocationStopsTheTokenInEveryConsumerDespiteTheCache(): void
    {
        $token = $this->tokens(self::RESOURCE)['access_token'];
        $this->assertSame(200, $this->send('GET', '/api/whoami', bearer: $token)->getStatusCode());
        $this->assertSame(200, $this->send('GET', '/oauth/openid/tenant1/userinfo', bearer: $token)->getStatusCode());

        $revoke = $this->send('POST', '/oauth/openid/tenant1/revoke', ['token' => $token, ...$this->clientCredentials()]);

        $this->assertSame(200, $revoke->getStatusCode());
        $this->assertInvalidToken($this->send('GET', '/api/whoami', bearer: $token));
        $this->assertInvalidToken($this->send('GET', '/oauth/openid/tenant1/userinfo', bearer: $token));
        $introspection = $this->send('POST', '/oauth/openid/tenant1/introspect', ['token' => $token, ...$this->clientCredentials()]);
        $this->assertSame(['active' => false], $this->decodeJson($introspection));
    }

    private function assertInvalidToken(ResponseInterface $response): void
    {
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Bearer error="invalid_token"', $response->getHeaderLine('WWW-Authenticate'));
    }

    /**
     * @param array<string, string> $body
     * @param array<string, string> $headers
     */
    private function send(string $method, string $path, array $body = [], ?string $bearer = null, array $headers = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, 'http://localhost' . $path);
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }
        if ($bearer !== null) {
            $headers['Authorization'] = 'Bearer ' . $bearer;
        }
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        return $this->app->handle($request);
    }

    /** @return array<string, string> */
    private function tokens(?string $audience = null): array
    {
        $response = $this->send('POST', '/oauth/openid/tenant1/token', array_filter([
            'grant_type' => 'password',
            'username' => self::USERNAME,
            'password' => self::PASSWORD,
            'scope' => 'openid profile',
            'audience' => $audience,
            ...$this->clientCredentials(),
        ]));
        $this->assertSame(200, $response->getStatusCode());
        return $this->decodeJson($response);
    }

    /** @return array<string, string> */
    private function clientCredentials(): array
    {
        return ['client_id' => self::CLIENT_ID, 'client_secret' => self::CLIENT_SECRET];
    }
}
