<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driven;

use Override;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\SimpleCache\CacheInterface;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\Gateway\RemoteJwksFetcher;

/**
 * Downloads a client's `jwks_uri` without letting the client point the server at internal
 * services (SSRF).
 *
 * Only `https` URIs without credentials are accepted. The host is resolved once and every
 * address must be public (no loopback, private, link-local, CGNAT or reserved range); the
 * connection is then pinned to the checked address, so a second DNS answer cannot redirect it
 * (DNS rebinding). Redirects are not followed, and the download is bounded in time and size.
 * Each document is cached per tenant, client and URI.
 */
class HttpRemoteJwksFetcher implements RemoteJwksFetcher
{
    private const int TIMEOUT_SECONDS = 5;
    private const int MAX_BYTES = 64 * 1024;
    private const int CACHE_SECONDS = 300;
    private const int HTTPS_PORT = 443;

    /**
     * @param ?ClientInterface $http        HTTP client; a default one when null (tests replace it).
     * @param ?\Closure        $resolveHost fn (string $host): list<string> of IPv4 addresses;
     *                                      DNS when null (tests replace it).
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ?ClientInterface $http = null,
        private readonly ?\Closure $resolveHost = null,
    ) {
    }

    #[Override]
    public function fetch(string $tenant, string $clientId, string $jwksUri): array
    {
        $key = 'oauth.jwks.' . hash('sha256', "{$tenant}\n{$clientId}\n{$jwksUri}");
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }
        $jwks = $this->download($jwksUri);
        $this->cache->set($key, $jwks, self::CACHE_SECONDS);
        return $jwks;
    }

    private function download(string $uri): array
    {
        $parts = parse_url($uri);
        if (!is_array($parts)
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || ($parts['host'] ?? '') === ''
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            throw new \InvalidArgumentException('jwks_uri must be an https URL without credentials');
        }
        $host = $parts['host'];
        $address = $this->publicAddress(trim($host, '[]'));
        $port = $parts['port'] ?? self::HTTPS_PORT;
        $pinned = str_contains($address, ':') ? "[{$address}]" : $address;
        try {
            $response = ($this->http ?? new Client())->request('GET', $uri, [
                'allow_redirects' => false,
                'connect_timeout' => self::TIMEOUT_SECONDS,
                'timeout' => self::TIMEOUT_SECONDS,
                'http_errors' => false,
                'stream' => true,
                'headers' => ['Accept' => 'application/json'],
                'curl' => [
                    CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinned}"],
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                ],
            ]);
        } catch (GuzzleException $ex) {
            throw new \InvalidArgumentException('jwks_uri download failed', 0, $ex);
        }
        if ($response->getStatusCode() !== 200) {
            throw new \InvalidArgumentException('jwks_uri answered ' . $response->getStatusCode());
        }
        $body = $response->getBody();
        $content = '';
        while (!$body->eof() && strlen($content) <= self::MAX_BYTES) {
            $content .= $body->read(8192);
        }
        if (strlen($content) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('jwks_uri document is too large');
        }
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('jwks_uri document is not a JSON object');
        }
        return $decoded;
    }

    /**
     * The address to connect to: the host itself when it is an IP literal, otherwise its DNS
     * answer. Every address must be public, not only the first one.
     */
    private function publicAddress(string $host): string
    {
        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : ($this->resolveHost ?? static fn (string $name): array => gethostbynamel($name) ?: [])($host);
        if ($addresses === []) {
            throw new \InvalidArgumentException('jwks_uri host does not resolve');
        }
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                throw new \InvalidArgumentException('jwks_uri host is not a public address');
            }
        }
        return $addresses[0];
    }
}
