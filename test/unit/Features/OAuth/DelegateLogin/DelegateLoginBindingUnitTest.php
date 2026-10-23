<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Civi\Lughauth\Shared\Context;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationRequest;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Client\Domain\ClientData;
use Civi\Lughauth\Features\OAuth\DelegateLogin\Application\DelegateLogin;
use Civi\Lughauth\Features\OAuth\DelegateLogin\Domain\DelegatedLoginEndpoint;
use Civi\Lughauth\Features\OAuth\DelegateLogin\Domain\DelegatedLoginProvider;
use Civi\Lughauth\Features\OAuth\DelegateLogin\Domain\DelegatedUserData;
use Civi\Lughauth\Features\OAuth\DelegateLogin\Domain\Gateway\DelegateLoginGateway;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\ReplayJtiGateway;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\ReplayPurpose;

/**
 * The callback data of a delegated login is only accepted by the browser that started it: the
 * state echoed by the provider must carry the binding kept in that browser's pre-session.
 */
#[AllowMockObjectsWithoutExpectations]
final class DelegateLoginBindingUnitTest extends TestCase
{
    private const string BINDING = 'binding-of-this-browser';

    public function testTheLoginStartedInThisBrowserIsAccepted(): void
    {
        [$delegated, $provider] = $this->delegated();
        $provider->expects($this->once())->method('authorize')->willReturn(new DelegatedUserData('sub-1', 'user@example.com', emailVerified: true));

        $result = $delegated->validateRedirection('/cb', 'saml-1', $this->callbackData(self::state()), 'tenant1', $this->request(), self::BINDING);

        $this->assertTrue($result?->valid);
    }

    public static function foreignStates(): array
    {
        return [
            'no binding in this browser' => [self::state(), ''],
            'binding of another browser' => [self::state(['delegation' => 'binding-of-the-attacker']), self::BINDING],
            'another provider' => [self::state(['provider' => 'other-provider']), self::BINDING],
            'another tenant' => [self::state(['tenant' => 'other-tenant']), self::BINDING],
            'another client' => [self::state(['client_id' => 'client-2']), self::BINDING],
            'no state at all' => ['', self::BINDING],
        ];
    }

    /**
     * The pre-session is a stateless cookie: a copy of it and of a valid callback (a SAML
     * assertion is valid for minutes) must not finish the login a second time.
     */
    public function testTheSameCallbackCannotFinishTheLoginTwice(): void
    {
        $replays = new InMemoryReplays();
        [$delegated, $provider] = $this->delegated($replays);
        $provider->expects($this->once())->method('authorize')->willReturn(new DelegatedUserData('sub-1', 'user@example.com', emailVerified: true));
        $data = $this->callbackData(self::state());

        $first = $delegated->validateRedirection('/cb', 'saml-1', $data, 'tenant1', $this->request(), self::BINDING);
        $second = $delegated->validateRedirection('/cb', 'saml-1', $data, 'tenant1', $this->request(), self::BINDING);

        $this->assertSame([true, null], [$first?->valid, $second]);
    }

    /**
     * The spent binding must not be reusable by presenting the same callback for another client
     * of the tenant: the state names the client, and the binding is spent for the whole tenant.
     */
    public function testTheSameCallbackCannotFinishALoginForAnotherClient(): void
    {
        [$delegated, $provider] = $this->delegated();
        $provider->expects($this->once())->method('authorize')->willReturn(new DelegatedUserData('sub-1', 'user@example.com', emailVerified: true));
        $data = $this->callbackData(self::state());

        $first = $delegated->validateRedirection('/cb', 'saml-1', $data, 'tenant1', $this->request(), self::BINDING);
        $other = $delegated->validateRedirection('/cb', 'saml-1', $data, 'tenant1', $this->request('client-2'), self::BINDING);

        $this->assertSame([true, null], [$first?->valid, $other]);
    }

    public function testABindingIsSpentForEveryClientOfTheTenant(): void
    {
        $replays = new InMemoryReplays();
        [$delegated, $provider] = $this->delegated($replays);
        $provider->expects($this->never())->method('authorize');
        // Already spent while finishing the login of client-1.
        $replays->consume('tenant1', '-', ReplayPurpose::DelegatedLogin, hash('sha256', self::BINDING), new \DateTimeImmutable('+10 minutes'));

        $this->assertNull($delegated->validateRedirection('/cb', 'saml-1', $this->callbackData(self::state(['client_id' => 'client-2'])), 'tenant1', $this->request('client-2'), self::BINDING));
    }

    #[DataProvider('foreignStates')]
    public function testCallbackDataOfAnotherFlowIsRefusedBeforeAskingTheProvider(string $state, string $binding): void
    {
        [$delegated, $provider] = $this->delegated();
        $provider->expects($this->never())->method('authorize');

        $this->assertNull($delegated->validateRedirection('/cb', 'saml-1', $this->callbackData($state), 'tenant1', $this->request(), $binding));
    }

    public function testTheCallbackPostsItsDataToTheAuthorizeStepWithoutPuttingItInTheUrl(): void
    {
        [$delegated] = $this->delegated();
        $state = self::state(['client_id' => 'client-1', 'redirect_uri' => 'https://rp/cb"><script>']);
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/oauth/openid/-/delegated/verify')
            ->withParsedBody(['SAMLResponse' => 'PHNhbWw+', 'RelayState' => $state]);

        $response = $delegated->redirectToTenant('/oauth/openid/', '/authorize', $request, new \Slim\Psr7\Response());
        $html = (string) $response->getBody();
        preg_match('/action="([^"]*)"/', $html, $action);
        $actionUrl = html_entity_decode($action[1] ?? '', ENT_QUOTES | ENT_HTML5);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringStartsWith('https://login.example/oauth/openid/tenant1/authorize?', $actionUrl);
        $this->assertStringContainsString('client_id=client-1', $actionUrl);
        $this->assertStringNotContainsString('delegation', $actionUrl, 'The binding stays out of the URL');
        $this->assertStringNotContainsString('SAMLResponse', $actionUrl, 'The provider data goes in the body');
        $this->assertStringNotContainsString('"><script>', $html, 'Values are escaped');
        $this->assertStringContainsString("form-action 'self'", $response->getHeaderLine('Content-Security-Policy'));
    }

    public function testACallbackWithoutStateIsABadRequest(): void
    {
        [$delegated] = $this->delegated();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/oauth/openid/-/delegated/verify?code=abc');

        $this->assertSame(400, $delegated->redirectToTenant('/oauth/openid/', '/authorize', $request, new \Slim\Psr7\Response())->getStatusCode());
    }

    public function testTheStateSentToTheProviderCarriesTheBinding(): void
    {
        [$delegated, $provider] = $this->delegated();
        $sent = null;
        $provider->method('delegeatedUrl')->willReturnCallback(function (string $redirect, string $state) use (&$sent): DelegatedLoginEndpoint {
            $sent = json_decode((string) base64_decode($state), true);
            return new DelegatedLoginEndpoint('GET', 'https://idp/sso');
        });

        $delegated->getTargetEndpoint('/cb', 'tenant1', 'saml-1', ['client_id' => 'client-1'], self::BINDING);

        $this->assertSame(['client_id' => 'client-1', 'tenant' => 'tenant1', 'provider' => 'saml-1', 'delegation' => self::BINDING], $sent);
    }

    private static function state(array $overrides = []): string
    {
        return base64_encode((string) json_encode($overrides + ['client_id' => 'client-1', 'tenant' => 'tenant1', 'provider' => 'saml-1', 'delegation' => self::BINDING]));
    }

    private function callbackData(string $state): string
    {
        return base64_encode((string) json_encode(['SAMLResponse' => 'PHNhbWw+', 'RelayState' => $state]));
    }

    private function request(string $clientId = 'client-1'): AuthenticationRequest
    {
        return new AuthenticationRequest(client: new ClientData($clientId, ['code'], true), scope: 'openid', redirect: 'https://rp/cb', responseType: 'code', audiences: [$clientId]);
    }

    /** @return array{0: DelegateLogin, 1: DelegatedLoginProvider&\PHPUnit\Framework\MockObject\MockObject} */
    private function delegated(?ReplayJtiGateway $replays = null): array
    {
        $context = $this->createMock(Context::class);
        $context->method('getBaseUrl')->willReturn('https://login.example');
        $provider = $this->createMock(DelegatedLoginProvider::class);
        $gateway = $this->createMock(DelegateLoginGateway::class);
        $gateway->method('getProvider')->willReturn($provider);
        $gateway->method('save')->willReturn(new AuthenticationResult(valid: true, id: 'user@example.com'));
        return [new DelegateLogin($context, $gateway, $replays ?? new InMemoryReplays()), $provider];
    }
}

final class InMemoryReplays implements ReplayJtiGateway
{
    /** @var array<string, true> */
    private array $used = [];

    public function consume(string $tenant, string $clientId, ReplayPurpose $purpose, string $jti, \DateTimeImmutable $expiresAt): bool
    {
        $key = implode('|', [$tenant, $clientId, $purpose->value, $jti]);
        if (isset($this->used[$key])) {
            return false;
        }
        return $this->used[$key] = true;
    }

    public function release(string $tenant, string $clientId, ReplayPurpose $purpose, string $jti): void
    {
        unset($this->used[implode('|', [$tenant, $clientId, $purpose->value, $jti])]);
    }

    public function purgeExpired(): void
    {
    }
}
