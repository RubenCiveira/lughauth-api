<?php

declare(strict_types=1);

use Civi\Lughauth\Features\Access\Tenant\Domain\Gateway\TenantQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\Tenant\Domain\Tenant;
use Civi\Lughauth\Features\Access\TrustedClient\Domain\Gateway\TrustedClientQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\TrustedClient\Domain\TrustedClient;
use Civi\Lughauth\Features\Access\User\Domain\Gateway\UserQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\User\Domain\User;
use Civi\Lughauth\Features\Access\UserConsentedScopes\Domain\Gateway\UserConsentedScopesCommandRepositoryGateway;
use Civi\Lughauth\Features\Access\UserConsentedScopes\Domain\UserConsentedScopes;
use Civi\Lughauth\Features\Access\UserConsentedScopes\Domain\UserConsentedScopesAttributes;
use Civi\Lughauth\Features\OAuth\Consent\Infrastructure\Driven\ScopesConsentAdapter;
use PHPUnit\Framework\TestCase;

final class ScopesConsentAdapterUnitTest extends TestCase
{
    /**
     * A scope declined on a new consent screen (prompt=consent) is withdrawn from the earlier
     * approval, so a later request asks for it again.
     */
    public function testDeclinedScopesAreWithdrawnFromTheEarlierApproval(): void
    {
        $stored = null;
        $existing = $this->createStub(UserConsentedScopes::class);
        $existing->method('getScope')->willReturn('openid email profile');
        $existing->method('update')->willReturnCallback(function (UserConsentedScopesAttributes $values) use (&$stored, $existing): UserConsentedScopes {
            $stored = $values->getScope();
            return $existing;
        });

        $this->adapter($existing)->storeAcceptedScopes('tenant1', 'user-1', 'client-123', ['openid', 'address'], ['profile']);

        $this->assertSame('openid email address', $stored);
    }

    private function adapter(UserConsentedScopes $existing): ScopesConsentAdapter
    {
        $tenants = $this->createStub(TenantQueryRepositoryGateway::class);
        $tenants->method('findOneByName')->willReturn($this->createStub(Tenant::class));
        $user = $this->createStub(User::class);
        $user->method('uid')->willReturn('user-uid');
        $users = $this->createStub(UserQueryRepositoryGateway::class);
        $users->method('findOneByTenantAndName')->willReturn($user);
        $client = $this->createStub(TrustedClient::class);
        $client->method('uid')->willReturn('client-uid');
        $clients = $this->createStub(TrustedClientQueryRepositoryGateway::class);
        $clients->method('retrieve')->willReturn($client);
        $consents = $this->createStub(UserConsentedScopesCommandRepositoryGateway::class);
        $consents->method('retrieve')->willReturn($existing);
        return new ScopesConsentAdapter($users, $tenants, $clients, $consents);
    }
}
