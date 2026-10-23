<?php

declare(strict_types=1);

use Civi\Lughauth\Features\OAuth\Client\Domain\ClientCredentials;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Which client authentication method a request uses, and the client it names (task 044).
 */
final class ClientCredentialsUnitTest extends TestCase
{
    public static function methods(): array
    {
        $basic = 'Basic ' . base64_encode('client:secret');
        $assertion = ['client_assertion_type' => ClientCredentials::ASSERTION_TYPE, 'client_assertion' => 'a.b.c'];
        return [
            'Basic header' => [$basic, [], [], 'client_secret_basic', 'client'],
            'Basic with the same client_id' => [$basic, [], ['client_id' => 'client'], 'client_secret_basic', 'client'],
            'PHP_AUTH server parameters' => ['', ['PHP_AUTH_USER' => 'client', 'PHP_AUTH_PW' => 'secret'], [], 'client_secret_basic', 'client'],
            'client_secret in the body' => ['', [], ['client_id' => 'client', 'client_secret' => 'secret'], 'client_secret_post', 'client'],
            'client assertion' => ['', [], $assertion, 'private_key_jwt', null],
            'client assertion with client_id' => ['', [], $assertion + ['client_id' => 'client'], 'private_key_jwt', 'client'],
            'client_id alone' => ['', [], ['client_id' => 'client'], 'none', 'client'],
            'other Authorization schemes are ignored' => ['Bearer token', [], ['client_id' => 'client'], 'none', 'client'],
            'nothing' => ['', [], [], null, null],
            'malformed Basic' => ['Basic %%%', [], ['client_id' => 'client'], null, 'client'],
            'Basic without colon' => ['Basic ' . base64_encode('client'), [], [], null, null],
            'Basic and client_secret' => [$basic, [], ['client_secret' => 'secret'], null, 'client'],
            'Basic and assertion' => [$basic, [], $assertion, null, 'client'],
            'client_secret and assertion' => ['', [], $assertion + ['client_id' => 'client', 'client_secret' => 'secret'], null, 'client'],
            'Basic of another client_id' => [$basic, [], ['client_id' => 'other'], null, null],
            'client_secret without client_id' => ['', [], ['client_secret' => 'secret'], null, null],
        ];
    }

    #[DataProvider('methods')]
    public function testMethodAndClient(string $authorization, array $server, array $body, ?string $method, ?string $clientId): void
    {
        $credentials = ClientCredentials::fromHttp($authorization, $server, $body);

        $this->assertSame([$method, $clientId], [$credentials->method(), $credentials->clientId()]);
    }

    public function testBasicCredentialsAreFormUrlDecoded(): void
    {
        $credentials = ClientCredentials::fromHttp('Basic ' . base64_encode('my%3Aclient:s%2Bcret'), [], []);

        $this->assertSame(['my:client', 's+cret'], [$credentials->clientId(), $credentials->secret()]);
    }
}
