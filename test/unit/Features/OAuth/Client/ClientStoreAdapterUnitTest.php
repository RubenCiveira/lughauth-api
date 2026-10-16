<?php

declare(strict_types=1);

namespace Tests\unit\Features\OAuth\Client;

use Civi\Lughauth\Features\Access\TrustedClient\Domain\Gateway\TrustedClientQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\TrustedClient\Domain\TrustedClient;
use Civi\Lughauth\Features\Access\TrustedClient\Domain\TrustedClientAttributes;
use Civi\Lughauth\Features\Access\TrustedClient\Domain\ValueObject\TrustedClientAllowedRedirectsItem;
use Civi\Lughauth\Features\Access\TrustedClient\Domain\ValueObject\TrustedClientAllowedRedirectsUrlVO;
use Civi\Lughauth\Features\OAuth\Client\Infrastructure\Driven\ClientStoreAdapter;
use Civi\Lughauth\Shared\Security\AesCypherService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class ClientStoreAdapterUnitTest extends TestCase
{
    public function testOnlyRegisteredRedirectsAreAccepted(): void
    {
        $store = $this->storeWithRedirect('https://app.example.com/callback');

        self::assertNull($store->publicClientData('angular-client', 'tenant1', 'http://local', 'openid'));
        self::assertNull($store->publicClientData('angular-client', 'tenant1', 'https://app.example.com/callback/', 'openid'));
        self::assertSame('none', $store->publicClientData('angular-client', 'tenant1', 'https://app.example.com/callback', 'openid')?->tokenEndpointAuthMethod);
    }

    public function testLocalhostWildcardDoesNotMatchAnotherHost(): void
    {
        $store = $this->storeWithRedirect('http://localhost:4200/*');

        self::assertNotNull($store->publicClientData('angular-client', 'tenant1', 'http://localhost:4200/callback', 'openid'));
        self::assertNull($store->publicClientData('angular-client', 'tenant1', 'http://localhost:4200.evil/callback', 'openid'));
    }

    private function storeWithRedirect(string $url): ClientStoreAdapter
    {
        $client = TrustedClient::create((new TrustedClientAttributes())
            ->withDefaults()
            ->uid('client-uid')
            ->code('angular-client')
            ->enabled(true)
            ->publicAllow(true)
            ->tokenEndpointAuthMethod('none')
            ->allowedRedirects([new TrustedClientAllowedRedirectsItem(
                null,
                TrustedClientAllowedRedirectsUrlVO::from($url),
                null,
            )]))->enable();
        $clients = $this->createStub(TrustedClientQueryRepositoryGateway::class);
        $clients->method('findOneByCode')->willReturn($client);

        $store = new ClientStoreAdapter($this->createStub(AesCypherService::class), $clients);
        $store->setLogger(new NullLogger());
        return $store;
    }
}
