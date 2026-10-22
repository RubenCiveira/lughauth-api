<?php

declare(strict_types=1);

namespace Tests\integration\Features\OAuth;

use Nimbly\Capsule\Response;
use Civi\Lughauth\Features\OAuth\Authentication\Application\ClaimsMapper;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationRequest;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\OidcFlowContext;
use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driver\Html\Services\OidcResponseBuilder;
use Civi\Lughauth\Features\OAuth\Oidc\Infrastructure\Driver\Rest\OpenIdConfigurationController;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\TokenSigner;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\TokenType;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\VerificationPolicy;
use Civi\Lughauth\Features\OAuth\User\Domain\PublicLoginAuthResponse;

/**
 * Tokens returned in the authorization response itself (hybrid flows) share the claim rules of the
 * token endpoint (EP-05); the response carries state and the discovery issuer as iss (EP-08).
 */
final class AuthorizationResponseTokensTest extends OidcIntegrationTestCase
{
    private const string SESSION = 'front-channel-session';
    private const string STATE = 'state-xyz';

    protected function setUp(): void
    {
        parent::setUp();
        $this->sessionStore->saveSession(
            self::SESSION,
            $this->clientStore->preValidatedClient(self::TENANT, self::CLIENT_ID),
            $this->context->getIssuer(self::TENANT),
            new ChallengesState(),
            new AuthenticationResult(valid: true, id: self::USER_ID),
            'csid',
            new \DateInterval('P1D'),
            ['pwd']
        );
    }

    public function testHybridIdTokenBindsTheCodeAndAccessTokenAndTheSession(): void
    {
        $fragment = $this->authorize('code id_token token');

        $id = $this->verify($fragment['id_token'], TokenType::Id);
        $this->assertSame(ClaimsMapper::hash($fragment['code']), $id['c_hash']);
        $this->assertSame(ClaimsMapper::hash($fragment['access_token']), $id['at_hash']);
        $this->assertSame(ClaimsMapper::hash(self::STATE), $id['s_hash']);
        $this->assertSame('nonce-abc', $id['nonce']);
        $this->assertSame(self::CLIENT_ID, $id['aud']);
        $this->assertSame($this->sessionStore->loadSession(self::SESSION)?->publicSid, $id['sid']);
        $this->assertSame(['pwd'], $id['amr']);
        $this->assertArrayHasKey('auth_time', $id);
        $this->assertArrayNotHasKey('session_state', $id);
        $this->assertNotNull($this->verify($fragment['access_token'], TokenType::Access));
        $this->assertSame('Bearer', $fragment['token_type']);
        $this->assertSame(self::STATE, $fragment['state']);
        $this->assertArrayNotHasKey('nonce', $fragment);
    }

    public function testIssIsTheIssuerOfTheTenantDiscovery(): void
    {
        $request = $this->makeRequest('GET', '/oauth/openid/' . self::TENANT . '/.well-known/openid-configuration');
        $discovery = $this->decodeJson($this->container->get(OpenIdConfigurationController::class)->get($request, new Response(200), ['tenant' => self::TENANT]));
        parse_str((string) parse_url($this->redirect('code')->getHeaderLine('Location'), PHP_URL_QUERY), $query);

        $this->assertTrue($discovery['authorization_response_iss_parameter_supported']);
        $this->assertSame($discovery['issuer'], $query['iss']);
        $this->assertSame($discovery['issuer'], $this->authorize('code id_token')['iss']);
    }

    public function testFormPostSendsTheHybridResponseInTheBody(): void
    {
        $response = $this->redirect('code id_token', mode: 'form_post');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('Location'));
        preg_match_all('/<input type="hidden" name="([^"]+)" value="([^"]*)"\/>/', (string) $response->getBody(), $fields);
        $this->assertSame(['code', 'id_token', 'state', 'iss'], $fields[1]);
        $id = $this->verify(html_entity_decode($fields[2][1]), TokenType::Id);
        $this->assertSame(ClaimsMapper::hash(html_entity_decode($fields[2][0])), $id['c_hash']);
    }

    public function testCodeOnlyResponseUsesTheQuery(): void
    {
        $parts = parse_url($this->redirect('code')->getHeaderLine('Location'));

        parse_str($parts['query'] ?? '', $query);
        $this->assertSame(self::STATE, $query['state']);
        $this->assertArrayHasKey('code', $query);
        $this->assertArrayNotHasKey('nonce', $query);
        $this->assertArrayNotHasKey('fragment', $parts);
    }

    public function testHybridIdTokenWithoutAccessTokenHasNoAtHash(): void
    {
        $fragment = $this->authorize('code id_token');

        $id = $this->verify($fragment['id_token'], TokenType::Id);
        $this->assertArrayNotHasKey('at_hash', $id);
        $this->assertSame(ClaimsMapper::hash($fragment['code']), $id['c_hash']);
        $this->assertArrayNotHasKey('access_token', $fragment);
    }

    public function testProfileClaimsFollowTheExactScope(): void
    {
        $id = $this->verify($this->authorize('code id_token', 'openid myprofile')['id_token'], TokenType::Id);

        $this->assertArrayNotHasKey('name', $id);
        $this->assertArrayNotHasKey('email', $id);
    }

    public function testRedirectNeverCarriesTheSessionCookie(): void
    {
        $location = $this->redirect('code id_token token')->getHeaderLine('Location');

        $this->assertStringNotContainsString(self::SESSION, $location);
        foreach (['id_token', 'access_token'] as $field) {
            $payload = $this->verify($this->fragment($location)[$field], $field === 'id_token' ? TokenType::Id : TokenType::Access);
            $this->assertStringNotContainsString(self::SESSION, (string) json_encode($payload), $field);
        }
    }

    /** @return array<string, string> response parameters of the redirect (query and fragment) */
    private function authorize(string $responseType, string $scope = 'openid profile email'): array
    {
        return $this->fragment($this->redirect($responseType, $scope)->getHeaderLine('Location'));
    }

    private function redirect(string $responseType, string $scope = 'openid profile email', ?string $mode = null): \Psr\Http\Message\ResponseInterface
    {
        $client = $this->clientStore->preValidatedClient(self::TENANT, self::CLIENT_ID);
        $this->assertNotNull($client);
        $issuer = $this->context->getIssuer(self::TENANT);
        $flow = new OidcFlowContext(
            tenant: self::TENANT,
            clientId: self::CLIENT_ID,
            redirect: 'https://app.example.com/callback',
            scope: $scope,
            responseType: $responseType,
            state: self::STATE,
            nonce: 'nonce-abc',
            audiences: [],
            prompt: '',
            locale: 'en',
            baseUrl: 'http://localhost',
            issuer: $issuer,
            sessionId: self::SESSION,
            preSessionId: null,
            codeChallenge: null,
            codeChallengeMethod: null,
            requestedResponseMode: $mode,
        );
        $auth = new PublicLoginAuthResponse(
            // The login result carries the tenant uid; tokens must still be signed with the tenant keys.
            tenant: self::TENANT_UIDS[self::TENANT],
            auth: new AuthenticationResult(
                valid: true,
                id: self::USER_ID,
                tenant: self::TENANT_UIDS[self::TENANT],
                tenantName: self::TENANT,
                scope: $scope,
                name: 'Test User',
                email: 'test@example.com',
            ),
            issuer: $issuer,
            clientId: self::CLIENT_ID,
            sessionId: self::SESSION,
            sessionExpiration: new \DateInterval('P1D'),
        );
        $request = new AuthenticationRequest($client, $scope, 'https://app.example.com/callback', $responseType);

        $response = $this->container->get(OidcResponseBuilder::class)
            ->buildSuccessRedirect($flow, $auth, $client, $request, new Response(200));
        $this->assertSame($mode === 'form_post' ? 200 : 302, $response->getStatusCode());
        return $response;
    }

    /** @return array<string, string> parameters of the fragment, the only place tokens may travel */
    private function fragment(string $location): array
    {
        $parts = parse_url($location);
        $this->assertArrayNotHasKey('query', $parts, 'a response with tokens uses only the fragment');
        parse_str($parts['fragment'] ?? '', $params);
        return array_map('strval', $params);
    }

    /** @return array<string, mixed> */
    private function verify(string $token, TokenType $type): array
    {
        $payload = $this->container->get(TokenSigner::class)->verify(self::TENANT, $token, VerificationPolicy::expecting($type));
        $this->assertNotNull($payload, 'signed with a key published by the tenant');
        return $payload;
    }
}
