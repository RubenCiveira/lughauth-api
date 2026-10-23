<?php

declare(strict_types=1);

use Civi\Lughauth\Features\OAuth\Authentication\Domain\Gateway\RemoteJwksFetcher;
use Civi\Lughauth\Features\OAuth\Client\Application\ClientAuthenticator;
use Civi\Lughauth\Features\OAuth\Client\Domain\ClientAuthPolicy;
use Civi\Lughauth\Features\OAuth\Client\Domain\ClientCredentials;
use Civi\Lughauth\Features\OAuth\Client\Domain\ClientData;
use Civi\Lughauth\Features\OAuth\Client\Domain\JwtAssertionVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\integration\Features\OAuth\Stub\InMemoryClientStore;
use Tests\integration\Features\OAuth\Stub\InMemoryReplayJti;

/**
 * Which secret-based methods each endpoint policy accepts (task 044). The assertions themselves
 * are covered by ClientAssertionTest.
 */
final class ClientAuthenticatorUnitTest extends TestCase
{
    private const string ISSUER = 'https://idp.example/oauth/openid/tenant1';

    public static function cases(): array
    {
        $basic = ['Basic ' . base64_encode('post-client:secret'), []];
        $post = ['', ['client_id' => 'post-client', 'client_secret' => 'secret']];
        $public = ['', ['client_id' => 'post-client']];
        $jwtSecret = ['', ['client_id' => 'jwt-client', 'client_secret' => 'secret']];
        $jwtPublic = ['', ['client_id' => 'jwt-client']];
        return [
            'registered method' => [ClientAuthPolicy::RegisteredMethod, $post, true],
            'another secret method than the registered one' => [ClientAuthPolicy::RegisteredMethod, $basic, false],
            'client_id alone of a confidential client' => [ClientAuthPolicy::RegisteredMethod, $public, false],
            'any secret method' => [ClientAuthPolicy::Secret, $basic, true],
            'client_id alone where a secret is needed' => [ClientAuthPolicy::Secret, $public, false],
            'client_id alone of a public client' => [ClientAuthPolicy::SecretOrPublic, $public, true],
            'secret of a private_key_jwt client' => [ClientAuthPolicy::Secret, $jwtSecret, false],
            'client_id alone of a private_key_jwt client' => [ClientAuthPolicy::SecretOrPublic, $jwtPublic, false],
        ];
    }

    /**
     * @param array{string, array} $request Authorization header and body
     */
    #[DataProvider('cases')]
    public function testPolicy(ClientAuthPolicy $policy, array $request, bool $authenticated): void
    {
        $clients = new InMemoryClientStore();
        $clients->register('post-client', 'secret', new ClientData('post-client', [], false, tokenEndpointAuthMethod: 'client_secret_post'));
        $clients->register('jwt-client', 'secret', new ClientData('jwt-client', [], false, tokenEndpointAuthMethod: 'private_key_jwt'));
        $authenticator = new ClientAuthenticator($clients, new JwtAssertionVerifier($this->createStub(RemoteJwksFetcher::class), new InMemoryReplayJti()));

        $client = $authenticator->authenticate('tenant1', ClientCredentials::fromHttp($request[0], [], $request[1]), $policy, self::ISSUER, self::ISSUER . '/token');

        $this->assertSame($authenticated, $client !== null);
    }
}
