<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Civi\Lughauth\Shared\AppConfig;
use Civi\Lughauth\Shared\Context;
use Civi\Lughauth\Shared\Exception\NotFoundException;
use Civi\Lughauth\Shared\Security\PasswordHasher;
use Civi\Lughauth\Features\Access\Tenant\Domain\Tenant;
use Civi\Lughauth\Features\Access\Tenant\Domain\TenantRef;
use Civi\Lughauth\Features\Access\TenantLoginProvider\Domain\Gateway\TenantLoginProviderQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\TenantLoginProvider\Domain\Gateway\TenantLoginProviderSlide;
use Civi\Lughauth\Features\Access\TenantLoginProvider\Domain\TenantLoginProvider;
use Civi\Lughauth\Features\Access\TenantLoginProvider\Domain\TenantLoginProviderAccountLinkPolicyOptions as Policy;
use Civi\Lughauth\Features\Access\TenantLoginProvider\Domain\TenantLoginProviderSourceOptions as Source;
use Civi\Lughauth\Features\Access\User\Domain\Gateway\UserCommandRepositoryGateway;
use Civi\Lughauth\Features\Access\User\Domain\User;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationRequest;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Client\Domain\ClientData;
use Civi\Lughauth\Features\OAuth\Common\Infrastructure\Driven\UserLoaderAdapter;
use Civi\Lughauth\Features\OAuth\DelegateLogin\Domain\DelegatedUserData;
use Civi\Lughauth\Features\OAuth\DelegateLogin\Domain\Gateway\FederatedIdentityGateway;
use Civi\Lughauth\Features\OAuth\DelegateLogin\Infrastructure\Driven\DelegateLoginAdapter;

/**
 * Which local account a federated sign-in opens. Links are per provider record: a linked subject
 * reaches its account; otherwise the verified email decides, under the provider's link policy.
 */
#[AllowMockObjectsWithoutExpectations]
final class DelegateLoginAdapterLinkingUnitTest extends TestCase
{
    private const string TENANT_UID = 'tenant-uid';
    private const string EMAIL = 'victim@example.com';

    private InMemoryFederatedIdentities $identities;
    /** @var array<string, User> accounts by name */
    private array $accounts = [];
    /** @var list<TenantLoginProvider> */
    private array $providers = [];
    private int $created = 0;

    protected function setUp(): void
    {
        $this->identities = new InMemoryFederatedIdentities();
    }

    // nOAuth: an IdP that lets anyone set the email must not open the owner's account.
    public function testAnUnverifiedEmailOpensNoAccount(): void
    {
        $this->account(self::EMAIL, null);
        $saml = $this->provider('saml-a', Source::SAML, Policy::VERIFIED_EMAIL);

        $this->assertRefused($this->signIn($saml, 'subject-1', verified: false));
        $this->assertSame(0, $this->created);
    }

    public function testALinkedSubjectReachesItsAccountWhateverTheEmail(): void
    {
        $account = $this->account('owner@example.com', 'saml');
        $saml = $this->provider('saml-a', Source::SAML, Policy::MANUAL);
        $this->identities->link('saml-a', 'subject-1', $account->uid() ?? '');

        $this->assertSame('owner@example.com', $this->signIn($saml, 'subject-1', verified: false, email: 'changed@example.com')->id);
    }

    public function testAVerifiedEmailJoinsAPasswordAccountUnderVerifiedEmailAndIsLinked(): void
    {
        $account = $this->account(self::EMAIL, null);
        $google = $this->provider('google-1', Source::GOOGLE, Policy::VERIFIED_EMAIL);

        $this->assertSame(self::EMAIL, $this->signIn($google, 'g-1')->id);
        $this->assertSame($account->uid(), $this->identities->linkedUser('google-1', 'g-1'));
    }

    public function testNoneKeepsAPasswordAccountOut(): void
    {
        $this->account(self::EMAIL, null);

        $this->assertRefused($this->signIn($this->provider('saml-a', Source::SAML, Policy::NONE), 'subject-1'));
    }

    public function testNoneKeepsTheAccountOfAnotherProviderOfTheSameTypeOut(): void
    {
        $account = $this->account(self::EMAIL, 'saml');
        $this->provider('saml-a', Source::SAML, Policy::NONE);
        $samlB = $this->provider('saml-b', Source::SAML, Policy::NONE);
        $this->identities->link('saml-a', 'subject-a', $account->uid() ?? '');

        $this->assertRefused($this->signIn($samlB, 'subject-b'));
    }

    public function testNoneLetsTheSameProviderReachItsAccountWithAnotherSubject(): void
    {
        $account = $this->account(self::EMAIL, 'saml');
        $samlA = $this->provider('saml-a', Source::SAML, Policy::NONE);
        $this->identities->link('saml-a', 'transient-1', $account->uid() ?? '');

        $this->assertSame(self::EMAIL, $this->signIn($samlA, 'transient-2')->id);
    }

    public function testAnAccountProvisionedBeforeLinksIsAttributedOnlyToTheSoleProviderOfItsType(): void
    {
        $this->account(self::EMAIL, 'saml');
        $samlA = $this->provider('saml-a', Source::SAML, Policy::NONE);

        $this->assertSame(self::EMAIL, $this->signIn($samlA, 'subject-1')->id);

        $this->identities = new InMemoryFederatedIdentities();
        $this->provider('saml-b', Source::SAML, Policy::NONE);
        $this->assertRefused($this->signIn($samlA, 'subject-1'), 'With two SAML IdPs, either could have provisioned it');
    }

    public function testManualNeitherLinksNorProvisions(): void
    {
        $manual = $this->provider('saml-a', Source::SAML, Policy::MANUAL);
        $this->assertRefused($this->signIn($manual, 'subject-1'));

        $this->account(self::EMAIL, 'saml');
        $this->assertRefused($this->signIn($manual, 'subject-1'));
        $this->assertSame(0, $this->created);
    }

    public function testANewVerifiedEmailIsProvisionedAndLinked(): void
    {
        $saml = $this->provider('saml-a', Source::SAML, Policy::NONE);

        $this->assertSame(self::EMAIL, $this->signIn($saml, 'subject-1')->id);
        $this->assertSame([1, 'created-1'], [$this->created, $this->identities->linkedUser('saml-a', 'subject-1')]);
    }

    /** Two first sign-ins of the same subject race: the link the store kept decides the account. */
    public function testALinkWonByAConcurrentSignInDecidesTheAccount(): void
    {
        $this->account(self::EMAIL, null);
        $winner = $this->account('winner@example.com', 'saml');
        $saml = $this->provider('saml-a', Source::SAML, Policy::VERIFIED_EMAIL);
        $this->identities->raceWinner = $winner->uid();

        $this->assertSame('winner@example.com', $this->signIn($saml, 'subject-1')->id);
    }

    public function testALinkWonByAnAccountOutsideTheTenantIsRefused(): void
    {
        $this->account(self::EMAIL, null);
        $saml = $this->provider('saml-a', Source::SAML, Policy::VERIFIED_EMAIL);
        $this->identities->raceWinner = 'uid-of-an-account-elsewhere';

        $this->assertRefused($this->signIn($saml, 'subject-1'));
    }

    public function testAnAssertionWithoutSubjectIsRefused(): void
    {
        $this->assertRefused($this->signIn($this->provider('saml-a', Source::SAML, Policy::VERIFIED_EMAIL), ''));
    }

    public function testASamlProviderWithoutIdpCertificateIsNotOffered(): void
    {
        $saml = $this->provider('saml-a', Source::SAML, Policy::VERIFIED_EMAIL);
        $saml->method('getSamlIdpSsoUrl')->willReturn('https://idp/sso');
        $saml->method('getSamlIdpEntityId')->willReturn('https://idp/metadata');
        $saml->method('getSamlIdpCert')->willReturn(null);
        $adapter = $this->adapter();

        $this->assertSame([], $adapter->providers('tenant1'));
        $this->expectException(NotFoundException::class);
        $adapter->getProvider('tenant1', 'saml-a');
    }

    private function signIn(TenantLoginProvider $provider, string $subject, bool $verified = true, string $email = self::EMAIL): AuthenticationResult
    {
        return $this->adapter($provider)->save(
            'tenant1',
            new AuthenticationRequest(client: new ClientData('client-1', ['code'], true), scope: 'openid', redirect: 'https://rp/cb', responseType: 'code', audiences: ['client-1']),
            new DelegatedUserData($subject, $email, emailVerified: $verified),
            $provider->uid() ?? ''
        );
    }

    private function assertRefused(AuthenticationResult $result, string $message = ''): void
    {
        $this->assertFalse($result->valid, $message);
    }

    private function account(string $name, ?string $provider): User
    {
        $tenant = $this->createMock(TenantRef::class);
        $tenant->method('uid')->willReturn(self::TENANT_UID);
        $account = $this->createMock(User::class);
        $account->method('uid')->willReturn('uid-of-' . $name);
        $account->method('getName')->willReturn($name);
        $account->method('getProvider')->willReturn($provider);
        $account->method('getTenant')->willReturn($tenant);
        return $this->accounts[$name] = $account;
    }

    private function provider(string $uid, Source $source, Policy $policy): TenantLoginProvider&\PHPUnit\Framework\MockObject\MockObject
    {
        $provider = $this->createMock(TenantLoginProvider::class);
        $provider->method('uid')->willReturn($uid);
        $provider->method('getSource')->willReturn($source);
        $provider->method('getAccountLinkPolicy')->willReturn($policy);
        $provider->method('isUsersEnabledByDefault')->willReturn(false);
        $provider->method('isDisabled')->willReturn(false);
        return $this->providers[] = $provider;
    }

    private function adapter(?TenantLoginProvider $signingIn = null): DelegateLoginAdapter
    {
        $tenant = $this->createMock(Tenant::class);
        $tenant->method('uid')->willReturn(self::TENANT_UID);
        $loader = $this->createMock(UserLoaderAdapter::class);
        $loader->method('checkTenant')->willReturn($tenant);

        $users = $this->createMock(UserCommandRepositoryGateway::class);
        $users->method('findOneForUpdateByTenantAndName')->willReturnCallback(fn ($t, string $name): ?User => $this->accounts[$name] ?? null);
        $users->method('findOneForUpdateByUid')->willReturnCallback(function (string $uid): ?User {
            foreach ($this->accounts as $account) {
                if ($account->uid() === $uid) {
                    return $account;
                }
            }
            return null;
        });
        $users->method('create')->willReturnCallback(function (): User {
            $this->created++;
            $created = $this->createMock(User::class);
            $created->method('uid')->willReturn('created-' . $this->created);
            $created->method('getName')->willReturn(self::EMAIL);
            return $created;
        });

        $providers = $this->createMock(TenantLoginProviderQueryRepositoryGateway::class);
        $providers->method('retrieve')->willReturnCallback(fn () => $signingIn ?? $this->providers[0] ?? null);
        $providers->method('list')->willReturnCallback(fn () => new TenantLoginProviderSlide(null, null, $this->providers));

        $config = $this->createMock(AppConfig::class);
        $config->method('get')->willReturn('https://assets.local');
        return new DelegateLoginAdapter($config, $this->createMock(Context::class), $loader, $users, new PasswordHasher(), $providers, $this->identities);
    }
}

final class InMemoryFederatedIdentities implements FederatedIdentityGateway
{
    /** @var array<string, string> user uid by "provider|subject" */
    private array $links = [];
    /** When set, a concurrent sign-in links the subject to this user just before link() inserts. */
    public ?string $raceWinner = null;

    public function linkedUser(string $providerUid, string $subject): ?string
    {
        return $this->links[$providerUid . '|' . $subject] ?? null;
    }

    public function isLinkedTo(string $userUid, string $providerUid): bool
    {
        foreach ($this->links as $key => $user) {
            if ($user === $userUid && str_starts_with($key, $providerUid . '|')) {
                return true;
            }
        }
        return false;
    }

    public function hasLinks(string $userUid): bool
    {
        return in_array($userUid, $this->links, true);
    }

    public function link(string $providerUid, string $subject, string $userUid): string
    {
        $key = $providerUid . '|' . $subject;
        if ($this->raceWinner !== null) {
            $this->links[$key] ??= $this->raceWinner;
        }
        return $this->links[$key] ??= $userUid;
    }
}
