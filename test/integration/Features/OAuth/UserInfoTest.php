<?php

declare(strict_types=1);

namespace Tests\integration\Features\OAuth;

use Nimbly\Capsule\Response;
use Psr\Http\Message\ResponseInterface;
use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driver\Rest\TokenController;
use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driver\Rest\UserInfoController;
use Civi\Lughauth\Features\OAuth\Profile\Domain\Gateway\ProfileGateway;
use Civi\Lughauth\Features\OAuth\Profile\Domain\OidcProfile;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\TokenSigner;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\TokenType;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\VerificationPolicy;

/**
 * UserInfo (task 035) and the bearer checks it shares with introspection and the middleware
 * (tasks 026 and 028): GET and POST, token type, issuer, audience, tenant and revocation.
 */
class UserInfoTest extends OidcIntegrationTestCase
{
    private UserInfoController $controller;
    private TokenSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();
        $profiles = $this->createStub(ProfileGateway::class);
        $profiles->method('findByUser')->willReturn(new OidcProfile(
            uid: 'profile-1',
            userUid: self::USER_ID,
            givenName: 'Ada',
            familyName: 'Lovelace',
            middleName: null,
            nickname: null,
            preferredUsername: null,
            pictureUrl: null,
            websiteUrl: null,
            gender: null,
            birthdate: null,
            zoneinfo: null,
            locale: null,
            phoneNumber: '+34 600 000 000',
            phoneNumberVerified: true,
            addressJson: '{"country":"ES"}',
            updatedAt: null,
            version: 1,
        ));
        $this->container->set(ProfileGateway::class, $profiles);
        $this->controller = $this->container->get(UserInfoController::class);
        $this->signer = $this->container->get(TokenSigner::class);
    }

    public function testGetAndPostReturnTheSameClaims(): void
    {
        $token = $this->passwordTokens('openid profile email phone address')['access_token'];

        $get = $this->userInfo('GET', $token);
        $post = $this->userInfo('POST', $token);
        $postBody = $this->userInfo('POST', null, ['access_token' => $token]);

        $this->assertSame(200, $get->getStatusCode());
        $claims = $this->decodeJson($get);
        $this->assertSame(self::USER_ID, $claims['sub']);
        $this->assertSame('Ada Lovelace', $claims['name']);
        $this->assertSame('+34 600 000 000', $claims['phone_number']);
        $this->assertSame(['country' => 'ES'], $claims['address']);
        $this->assertArrayNotHasKey('email_verified', $claims);
        $this->assertSame($claims, $this->decodeJson($post));
        $this->assertSame($claims, $this->decodeJson($postBody));
    }

    public function testClaimsFollowTheGrantedScopes(): void
    {
        $claims = $this->decodeJson($this->userInfo('GET', $this->passwordTokens('openid')['access_token']));

        $this->assertSame(['sub' => self::USER_ID], $claims);
    }

    public function testAccessTokenWithoutOpenidScopeIsInsufficient(): void
    {
        $response = $this->userInfo('GET', $this->passwordTokens('profile')['access_token']);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Bearer error="insufficient_scope", scope="openid"', $response->getHeaderLine('WWW-Authenticate'));
    }

    public function testMissingTokenIsChallenged(): void
    {
        $this->assertInvalidToken($this->userInfo('GET', null));
    }

    public function testIdAndRefreshTokensAreNotAccessTokens(): void
    {
        $tokens = $this->passwordTokens('openid profile offline_access');

        $this->assertInvalidToken($this->userInfo('GET', $tokens['id_token']));
        $this->assertInvalidToken($this->userInfo('POST', $tokens['refresh_token']));
    }

    public function testRevokedTokenIsRejectedAlsoAfterACachedValidation(): void
    {
        $token = $this->passwordTokens('openid')['access_token'];
        $this->assertSame(200, $this->userInfo('GET', $token)->getStatusCode());

        $jti = $this->payload($token)['jti'];
        $this->tokenRevocation->revokeJti($jti, self::TENANT, 'access', new \DateTimeImmutable('+10 minutes'));

        $this->assertInvalidToken($this->userInfo('GET', $token));
    }

    public function testValidTokenOfAnotherTenantIsRejected(): void
    {
        $token = $this->passwordTokens('openid')['access_token'];

        $this->assertInvalidToken($this->userInfo('GET', $token, tenant: 'tenant2'));
    }

    public function testClientCredentialsTokenIsAcceptedForItsTenant(): void
    {
        $response = $this->controller->get(
            $this->makeRequest('GET', '/oauth/openid/tenant1/userinfo')->withHeader('Authorization', 'Bearer ' . $this->clientCredentialsToken()),
            new Response(200),
            ['tenant' => self::TENANT]
        );

        $this->assertSame(['sub' => self::CLIENT_ID], $this->decodeJson($response));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function forgedClaims(): array
    {
        return [
            'foreign issuer' => [['iss' => 'http://localhost/oauth/openid/tenant2']],
            'no issuer' => [['iss' => null]],
            'no audience' => [['aud' => null]],
            'no tenant' => [['tenant' => null]],
            'another tenant' => [['tenant' => 'tenant2-uid']],
        ];
    }

    /**
     * Correctly signed with the tenant's key, yet not an access token of this tenant.
     *
     * @param array<string, mixed> $override
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('forgedClaims')]
    public function testSignedTokenWithWrongClaimsIsRejected(array $override): void
    {
        $claims = array_filter([
            'iss' => $this->context->getIssuer(self::TENANT),
            'sub' => self::USER_ID,
            'aud' => self::CLIENT_ID,
            'tenant' => self::TENANT_UIDS[self::TENANT],
            'scope' => 'openid',
            'jti' => 'forged-jti',
            ...$override,
        ], static fn (mixed $value): bool => $value !== null);
        $token = $this->signer->sign(TokenType::Access, $claims, self::TENANT, new \DateInterval('PT10M'));

        $this->assertInvalidToken($this->userInfo('GET', $token));
    }

    private function assertInvalidToken(ResponseInterface $response): void
    {
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Bearer error="invalid_token"', $response->getHeaderLine('WWW-Authenticate'));
        $this->assertSame('invalid_token', $this->decodeJson($response)['error']);
    }

    /** @param array<string, string> $body */
    private function userInfo(string $method, ?string $bearer, array $body = [], string $tenant = self::TENANT): ResponseInterface
    {
        $request = $this->makeRequest($method, "/oauth/openid/{$tenant}/userinfo", $body);
        if ($bearer !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $bearer);
        }
        $action = $method === 'POST' ? 'post' : 'get';
        return $this->controller->$action($request, new Response(200), ['tenant' => $tenant]);
    }

    /** @return array<string, string> */
    private function passwordTokens(string $scope): array
    {
        $response = $this->container->get(TokenController::class)->post($this->makeRequest('POST', '/oauth/openid/tenant1/token', [
            'grant_type' => 'password',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
            'username' => self::USERNAME,
            'password' => self::PASSWORD,
            'scope' => $scope,
        ]), new Response(200), ['tenant' => self::TENANT]);
        $this->assertSame(200, $response->getStatusCode());
        return $this->decodeJson($response);
    }

    private function clientCredentialsToken(): string
    {
        $response = $this->container->get(TokenController::class)->post($this->makeRequest('POST', '/oauth/openid/tenant1/token', [
            'grant_type' => 'client_credentials',
            'client_id' => self::CLIENT_ID,
            'client_secret' => self::CLIENT_SECRET,
            'scope' => 'openid',
        ]), new Response(200), ['tenant' => self::TENANT]);
        return $this->decodeJson($response)['access_token'];
    }

    /** @return array<string, mixed> */
    private function payload(string $token): array
    {
        return $this->signer->verify(self::TENANT, $token, VerificationPolicy::expecting(TokenType::Access)) ?? [];
    }
}
