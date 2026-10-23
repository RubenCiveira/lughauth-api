<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\DelegateLogin\Infrastructure\Driven;

use Override;
use Ramsey\Uuid\Uuid;
use Civi\Lughauth\Shared\Exception\ConstraintException;
use Civi\Lughauth\Features\Access\TenantLoginProvider\Domain\TenantLoginProviderRef;
use Civi\Lughauth\Features\Access\User\Domain\UserRef;
use Civi\Lughauth\Features\Access\UserFederatedIdentity\Domain\Gateway\UserFederatedIdentityCommandRepositoryGateway;
use Civi\Lughauth\Features\Access\UserFederatedIdentity\Domain\Gateway\UserFederatedIdentityFilter;
use Civi\Lughauth\Features\Access\UserFederatedIdentity\Domain\UserFederatedIdentity;
use Civi\Lughauth\Features\Access\UserFederatedIdentity\Domain\UserFederatedIdentityAttributes;
use Civi\Lughauth\Features\OAuth\DelegateLogin\Domain\Gateway\FederatedIdentityGateway;

/**
 * FederatedIdentityGateway over the `user-federated-identity` entity of the access model, so
 * links can also be listed, created and removed through the access API (what the `manual`
 * account link policy relies on) and are audited like any other access entity.
 *
 * The unique index (login-provider, subject) keeps one account per subject: when two first
 * sign-ins of the same subject race, the second create fails as not unique and the link that
 * won is read back.
 */
class FederatedIdentityAdapter implements FederatedIdentityGateway
{
    public function __construct(private readonly UserFederatedIdentityCommandRepositoryGateway $identities)
    {
    }

    #[Override]
    public function linkedUser(string $providerUid, string $subject): ?string
    {
        return $this->identities->findOneByLoginProviderAndSubject(new TenantLoginProviderRef($providerUid), $subject)?->getUser()->uid();
    }

    #[Override]
    public function isLinkedTo(string $userUid, string $providerUid): bool
    {
        return $this->identities->exists(new UserFederatedIdentityFilter(user: new UserRef($userUid), loginProvider: new TenantLoginProviderRef($providerUid)));
    }

    #[Override]
    public function hasLinks(string $userUid): bool
    {
        return $this->identities->exists(new UserFederatedIdentityFilter(user: new UserRef($userUid)));
    }

    #[Override]
    public function link(string $providerUid, string $subject, string $userUid): string
    {
        try {
            $this->identities->create(UserFederatedIdentity::create(
                (new UserFederatedIdentityAttributes())
                    ->withDefaults()
                    ->uid(Uuid::uuid4()->toString())
                    ->user(new UserRef($userUid))
                    ->loginProvider(new TenantLoginProviderRef($providerUid))
                    ->subject($subject)
            ));
            return $userUid;
        } catch (ConstraintException $ex) {
            if (!$ex->includeViolationCode('not-unique')) {
                throw $ex;
            }
            // Another sign-in linked the subject first: that link stands.
            return $this->linkedUser($providerUid, $subject) ?? throw $ex;
        }
    }
}
