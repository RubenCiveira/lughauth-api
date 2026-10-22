<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\OidcFlowContext;
use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driver\Html\Services\AuthorizationResponseWriter;

/**
 * Unit tests for {@see AuthorizationResponseWriter} (tasks 027, 037 and 045).
 */
final class AuthorizationResponseWriterUnitTest extends TestCase
{
    private const string ISSUER = 'https://idp.example/oauth/openid/tenant1';

    public function testCodeSuccessGoesInTheQueryWithStateAndIssButNoNonce(): void
    {
        $response = (new AuthorizationResponseWriter())->success($this->flow('code'), ['code' => 'c1'], new Response());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(['code' => 'c1', 'state' => 'st', 'iss' => self::ISSUER], $this->query($response->getHeaderLine('Location')));
    }

    public function testHybridErrorGoesInTheFragment(): void
    {
        $location = (new AuthorizationResponseWriter())->error($this->flow('code id_token'), 'access_denied', new Response())->getHeaderLine('Location');

        $this->assertNull(parse_url($location, PHP_URL_QUERY));
        parse_str((string) parse_url($location, PHP_URL_FRAGMENT), $fragment);
        $this->assertSame(['error' => 'access_denied', 'state' => 'st', 'iss' => self::ISSUER], $fragment);
    }

    public function testInvalidRequestedModeStillAnswersInTheDefaultMode(): void
    {
        $location = (new AuthorizationResponseWriter())->error($this->flow('code id_token', 'query'), 'invalid_request', new Response())->getHeaderLine('Location');

        $this->assertStringStartsWith('https://rp.example/cb#error=invalid_request', $location);
    }

    public function testStateIsOmittedWhenTheClientSentNone(): void
    {
        $location = (new AuthorizationResponseWriter())->error($this->flow('code', state: ''), 'login_required', new Response())->getHeaderLine('Location');

        $this->assertSame(['error' => 'login_required', 'iss' => self::ISSUER], $this->query($location));
    }

    public function testFormPostPostsEscapedFieldsUnderAStrictPolicy(): void
    {
        $flow = $this->flow('code id_token', 'form_post', state: '"><script>alert(1)</script>', redirect: 'https://rp.example:8443/cb?x=1&y=2');

        $response = (new AuthorizationResponseWriter())->success($flow, ['code' => 'c1', 'id_token' => 'h.p.s'], new Response());
        $html = (string) $response->getBody();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($response->hasHeader('Location'));
        $this->assertStringContainsString('<form method="post" action="https://rp.example:8443/cb?x=1&amp;y=2">', $html);
        $this->assertStringContainsString('<input type="hidden" name="id_token" value="h.p.s"/>', $html);
        $this->assertStringContainsString('<input type="hidden" name="iss" value="' . self::ISSUER . '"/>', $html);
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringContainsString('value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $html);
        $this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $csp = $response->getHeaderLine('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString('form-action https://rp.example:8443;', $csp);
        $this->assertMatchesRegularExpression("/script-src 'sha256-[A-Za-z0-9+\\/=]+';/", $csp);
    }

    public function testStateIsSentOnceEvenIfTheRegisteredUriHasOne(): void
    {
        $writer = new AuthorizationResponseWriter();
        $redirect = 'https://rp.example/cb?state=old&x=1';

        $location = $writer->success($this->flow('code', redirect: $redirect), ['code' => 'c1'], new Response())->getHeaderLine('Location');
        $html = (string) $writer->success($this->flow('code', 'form_post', redirect: $redirect), ['code' => 'c1'], new Response())->getBody();

        $this->assertSame(1, substr_count($location, 'state='));
        $this->assertSame(['x' => '1', 'code' => 'c1', 'state' => 'st', 'iss' => self::ISSUER], $this->query($location));
        $this->assertStringContainsString('action="https://rp.example/cb?x=1"', $html);
    }

    public function testFormPostToANativeAppSchemeAllowsOnlyThatScheme(): void
    {
        $response = (new AuthorizationResponseWriter())->error($this->flow('code', 'form_post', redirect: 'com.example.app:/cb'), 'access_denied', new Response());

        $this->assertStringContainsString('form-action com.example.app:;', $response->getHeaderLine('Content-Security-Policy'));
    }

    public function testErrorButtonFollowsTheMode(): void
    {
        $writer = new AuthorizationResponseWriter();

        $link = $writer->errorButton($this->flow('code'), 'access_denied', 'Back');
        $form = $writer->errorButton($this->flow('code', 'form_post'), 'access_denied', 'Back');

        $this->assertSame('<a class="primary-button" href="https://rp.example/cb?error=access_denied&amp;state=st&amp;iss=' . rawurlencode(self::ISSUER) . '">Back</a>', $link);
        $this->assertStringStartsWith('<form method="post" action="https://rp.example/cb"><input type="hidden" name="error" value="access_denied"/>', $form);
    }

    private function flow(string $responseType, ?string $mode = null, string $state = 'st', string $redirect = 'https://rp.example/cb'): OidcFlowContext
    {
        return new OidcFlowContext(
            tenant: 'tenant1',
            clientId: 'client',
            redirect: $redirect,
            scope: 'openid',
            responseType: $responseType,
            state: $state,
            nonce: 'n-1',
            audiences: [],
            prompt: '',
            locale: 'en',
            baseUrl: 'https://idp.example',
            issuer: self::ISSUER,
            sessionId: null,
            preSessionId: null,
            codeChallenge: null,
            codeChallengeMethod: null,
            requestedResponseMode: $mode,
        );
    }

    /** @return array<string, string> */
    private function query(string $location): array
    {
        $this->assertNull(parse_url($location, PHP_URL_FRAGMENT));
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        return $query;
    }
}
