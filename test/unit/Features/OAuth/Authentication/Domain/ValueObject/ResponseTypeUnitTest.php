<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject\ResponseMode;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject\ResponseType;

/**
 * Unit tests for {@see ResponseType} and {@see ResponseMode} (EP-08).
 */
final class ResponseTypeUnitTest extends TestCase
{
    public function testResponseTypeIsASetInCanonicalOrder(): void
    {
        $this->assertSame('code id_token token', ResponseType::fromString(' token  id_token code code ')->toString());
        $this->assertSame('code magic', ResponseType::fromString('magic code')->toString());
    }

    public static function supportedCases(): array
    {
        return [
            'code' => ['code', true],
            'hybrid in any order' => ['id_token code', true],
            'hybrid with both tokens' => ['token code id_token', true],
            'implicit id_token' => ['id_token', false],
            'implicit token' => ['token', false],
            'implicit both' => ['id_token token', false],
            'none' => ['none', false],
            'unknown part' => ['code magic', false],
            'empty' => ['', false],
        ];
    }

    #[DataProvider('supportedCases')]
    public function testOnlyCodeAndHybridAreSupported(string $responseType, bool $supported): void
    {
        $this->assertSame($supported, ResponseType::fromString($responseType)->isSupported());
    }

    public function testRegisteredResponseTypesMatchInAnyOrder(): void
    {
        $hybrid = ResponseType::fromString('code id_token');

        $this->assertTrue($hybrid->isAmong(['code', 'id_token code']));
        $this->assertFalse($hybrid->isAmong(['code', 'code id_token token']));
        $this->assertFalse(ResponseType::fromString('code')->isAmong([]));
    }

    public static function modeCases(): array
    {
        return [
            'code defaults to query' => ['code', null, ResponseMode::Query],
            'hybrid defaults to fragment' => ['code id_token', null, ResponseMode::Fragment],
            'code may use fragment' => ['code', 'fragment', ResponseMode::Fragment],
            'code may use form_post' => ['code', 'form_post', ResponseMode::FormPost],
            'hybrid may use form_post' => ['code token', 'form_post', ResponseMode::FormPost],
            'tokens never in the query' => ['code id_token', 'query', null],
            'unknown mode' => ['code', 'jwt', null],
        ];
    }

    #[DataProvider('modeCases')]
    public function testResponseModeFollowsTheResponseType(string $responseType, ?string $requested, ?ResponseMode $expected): void
    {
        $this->assertSame($expected, ResponseMode::resolve(ResponseType::fromString($responseType), $requested));
    }

    public static function uriCases(): array
    {
        $params = ['code' => 'c', 'state' => 'a&b=c #d', 'iss' => 'https://idp/oauth/openid/t1'];
        $encoded = 'code=c&state=a%26b%3Dc%20%23d&iss=https%3A%2F%2Fidp%2Foauth%2Fopenid%2Ft1';
        return [
            'query' => [ResponseMode::Query, 'https://rp/cb', $params, "https://rp/cb?$encoded"],
            'query after the registered one' => [ResponseMode::Query, 'https://rp/cb?tenant=a', $params, "https://rp/cb?tenant=a&$encoded"],
            'query before a fragment' => [ResponseMode::Query, 'https://rp/cb?x=1#app', $params, "https://rp/cb?x=1&$encoded#app"],
            'fragment' => [ResponseMode::Fragment, 'https://rp/cb?tenant=a', $params, "https://rp/cb?tenant=a#$encoded"],
            'fragment after the registered one' => [ResponseMode::Fragment, 'https://rp/cb#app', $params, "https://rp/cb#app&$encoded"],
            'form_post keeps the uri' => [ResponseMode::FormPost, 'https://rp/cb?tenant=a', $params, 'https://rp/cb?tenant=a'],
            'registered state is replaced, not repeated' => [ResponseMode::Query, 'https://rp/cb?state=old&x=1&iss=y', $params, "https://rp/cb?x=1&$encoded"],
            'registered duplicate leaves no empty query' => [ResponseMode::Fragment, 'https://rp/cb?state=old#code=z', $params, "https://rp/cb#$encoded"],
            'form_post drops registered duplicates' => [ResponseMode::FormPost, 'https://rp/cb?state=old&x=1', $params, 'https://rp/cb?x=1'],
            'encoded names count as duplicates' => [ResponseMode::Query, 'https://rp/cb?st%61te=old', $params, "https://rp/cb?$encoded"],
        ];
    }

    /** @param array<string, string> $params */
    #[DataProvider('uriCases')]
    public function testRedirectUriEncodesTheParametersAndKeepsTheRegisteredOnes(ResponseMode $mode, string $redirect, array $params, string $expected): void
    {
        $this->assertSame($expected, $mode->redirectUri($redirect, $params));
    }

    public function testReservedCharactersInStateDoNotCreateParameters(): void
    {
        $uri = ResponseMode::Query->redirectUri('https://rp/cb', ['code' => 'real', 'state' => 'x&code=evil&iss=https://evil']);

        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
        $this->assertSame(['code' => 'real', 'state' => 'x&code=evil&iss=https://evil'], $query);
    }
}
