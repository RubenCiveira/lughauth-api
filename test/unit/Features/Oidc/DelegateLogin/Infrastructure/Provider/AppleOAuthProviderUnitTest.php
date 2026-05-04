<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Civi\Lughauth\Shared\AppConfig;
use Civi\Lughauth\Shared\Context;
use Civi\Lughauth\Features\Oidc\DelegateLogin\Infrastructure\Provider\AppleOAuthProvider;

#[AllowMockObjectsWithoutExpectations]
final class AppleOAuthProviderUnitTest extends TestCase
{
    public function testGenerateClientSecretReturnsJwt(): void
    {
        $provider = $this->createProvider($this->createMock(ClientInterface::class));
        $method = new ReflectionMethod(AppleOAuthProvider::class, 'generateClientSecret');
        $token = (string) $method->invoke($provider);

        $parts = explode('.', $token);
        $this->assertCount(3, $parts);

        $header = json_decode((string) base64_decode(strtr($parts[0], '-_', '+/')), true);
        $this->assertEquals('ES256', $header['alg'] ?? null);
    }

    public function testAuthorizeMapsFirstLoginName(): void
    {
        $jwt = $this->fakeJwt([
            'sub' => 'apple-1',
            'email' => 'apple@example.com',
            'email_verified' => true,
            'nonce' => hash('sha256', 'state-123'),
        ]);

        $http = $this->createMock(ClientInterface::class);
        $http->method('request')->willReturn(new Response(200, [], '{"id_token":"' . $jwt . '"}'));

        $provider = $this->createProvider($http);
        $user = $provider->authorize('https://app.local/delegate/callback', [
            'code' => 'oauth-code',
            'state' => 'state-123',
            'user' => '{"name":{"firstName":"Ana","lastName":"Apple"}}',
        ]);

        $this->assertNotNull($user);
        $this->assertEquals('Ana Apple', $user->name);
        $this->assertEquals('Ana', $user->givenName);
        $this->assertEquals('Apple', $user->familyName);
    }

    private function createProvider(ClientInterface $http): AppleOAuthProvider
    {
        $config = $this->createMock(AppConfig::class);
        $config->method('get')->willReturn('https://assets.local');

        $context = $this->createMock(Context::class);
        $context->method('getBaseUrl')->willReturn('https://app.local');

        return new AppleOAuthProvider(
            $config,
            $context,
            'apple-provider',
            'com.example.app',
            'TEAMID123',
            'KEYID123',
            $this->createEcPrivateKeyPem(),
            $http
        );
    }

    private function fakeJwt(array $payload): string
    {
        $header = rtrim(strtr(base64_encode('{"alg":"none","typ":"JWT"}'), '+/', '-_'), '=');
        $body = rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');
        return $header . '.' . $body . '.sig';
    }

    private function createEcPrivateKeyPem(): string
    {
        $resource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        if ($resource === false) {
            throw new RuntimeException('Unable to generate EC key for test');
        }
        $private = '';
        openssl_pkey_export($resource, $private);
        return $private;
    }
}
