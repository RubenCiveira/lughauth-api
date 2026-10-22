<?php

declare(strict_types=1);

use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driven\HttpRemoteJwksFetcher;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * Unit tests for {@see HttpRemoteJwksFetcher}: SSRF refusals, pinned connection, limits and cache.
 */
final class HttpRemoteJwksFetcherUnitTest extends TestCase
{
    private const string JWKS = '{"keys":[{"kty":"RSA","n":"AQAB","e":"AQAB"}]}';
    private const string PUBLIC_ADDRESS = '93.184.215.14';

    /** Requests that reached the HTTP client, with their options. */
    private array $sent = [];

    public static function refusedUris(): array
    {
        return [
            'plain http' => ['http://client.example/jwks'],
            'credentials in the URI' => ['https://user:pass@client.example/jwks'],
            'loopback literal' => ['https://127.0.0.1/jwks'],
            'private literal' => ['https://10.0.0.5/jwks'],
            'cloud metadata' => ['https://169.254.169.254/latest/meta-data'],
            'IPv6 loopback' => ['https://[::1]/jwks'],
            'not a URL' => ['jwks'],
        ];
    }

    #[DataProvider('refusedUris')]
    public function testRefusesUrisThatCouldReachInternalServices(string $uri): void
    {
        $fetcher = $this->fetcher([new Response(200, [], self::JWKS)]);

        try {
            $fetcher->fetch('tenant1', 'client-123', $uri);
            $this->fail("$uri must be refused");
        } catch (InvalidArgumentException) {
            $this->assertSame([], $this->sent, 'A refused URI must not be requested');
        }
    }

    public function testRefusesHostResolvingToAPrivateAddress(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], self::JWKS)], [self::PUBLIC_ADDRESS, '10.1.2.3']);

        $this->expectException(InvalidArgumentException::class);
        $fetcher->fetch('tenant1', 'client-123', 'https://rebind.example/jwks');
    }

    public function testRefusesHostThatDoesNotResolve(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], self::JWKS)], []);

        $this->expectException(InvalidArgumentException::class);
        $fetcher->fetch('tenant1', 'client-123', 'https://missing.example/jwks');
    }

    public function testDownloadsPublicHostPinnedToTheCheckedAddressWithoutRedirects(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], self::JWKS)]);

        $jwks = $fetcher->fetch('tenant1', 'client-123', 'https://client.example:8443/jwks');

        $this->assertSame(json_decode(self::JWKS, true), $jwks);
        [, $options] = $this->sent[0];
        $this->assertSame(['client.example:8443:' . self::PUBLIC_ADDRESS], $options['curl'][CURLOPT_RESOLVE]);
        $this->assertFalse($options['allow_redirects']);
    }

    public function testDoesNotFollowRedirects(): void
    {
        $fetcher = $this->fetcher([new Response(302, ['Location' => 'http://127.0.0.1/jwks'])]);

        $this->expectException(InvalidArgumentException::class);
        $fetcher->fetch('tenant1', 'client-123', 'https://client.example/jwks');
    }

    public function testRefusesDocumentsOverTheSizeLimit(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], '{"keys":[' . str_repeat(' ', 70 * 1024) . ']}')]);

        $this->expectException(InvalidArgumentException::class);
        $fetcher->fetch('tenant1', 'client-123', 'https://client.example/jwks');
    }

    public function testCachesTheDocumentPerClient(): void
    {
        $fetcher = $this->fetcher([new Response(200, [], self::JWKS), new Response(200, [], self::JWKS)]);

        $fetcher->fetch('tenant1', 'client-123', 'https://client.example/jwks');
        $fetcher->fetch('tenant1', 'client-123', 'https://client.example/jwks');
        $this->assertCount(1, $this->sent);

        $fetcher->fetch('tenant1', 'other-client', 'https://client.example/jwks');
        $this->assertCount(2, $this->sent);
    }

    /**
     * @param list<Response> $responses
     * @param list<string>   $addresses DNS answer for any host name
     */
    private function fetcher(array $responses, array $addresses = [self::PUBLIC_ADDRESS]): HttpRemoteJwksFetcher
    {
        $queue = array_map(fn (Response $response): Closure => function (RequestInterface $request, array $options) use ($response): Response {
            $this->sent[] = [$request, $options];
            return $response;
        }, $responses);
        return new HttpRemoteJwksFetcher(
            new Psr16Cache(new ArrayAdapter()),
            new Client(['handler' => HandlerStack::create(new MockHandler($queue))]),
            static fn (string $host): array => $addresses,
        );
    }
}
