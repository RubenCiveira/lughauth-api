<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\DelegateLogin\Application\Listener;

use Civi\Lughauth\Shared\Value\Validation\ConstraintFail;
use Civi\Lughauth\Features\Access\TenantLoginProvider\Domain\Gateway\TenantLoginProviderQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\User\Domain\Gateway\UserQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\UserFederatedIdentity\Application\Usecase\Create\UserFederatedIdentityCreateCheck;
use Civi\Lughauth\Features\Access\UserFederatedIdentity\Application\Usecase\Update\UserFederatedIdentityUpdateCheck;

/**
 * A federated identity link joins a login provider and a user of the same tenant.
 *
 * The access API only checks that the user is visible to the caller. Without this check an
 * administrator of one tenant could link a provider of another tenant to one of their users:
 * that grants no access (a sign-in only opens accounts of the provider's tenant), but the
 * unique (login-provider, subject) index would reserve the subject, and the real user of the
 * other tenant could no longer sign in with that provider.
 */
final class FederatedIdentityWithinOneTenant
{
    public function __construct(
        private readonly UserQueryRepositoryGateway $users,
        private readonly TenantLoginProviderQueryRepositoryGateway $providers,
    ) {
    }

    public function __invoke(UserFederatedIdentityCreateCheck|UserFederatedIdentityUpdateCheck $check): UserFederatedIdentityCreateCheck|UserFederatedIdentityUpdateCheck
    {
        $current = $check instanceof UserFederatedIdentityUpdateCheck ? $check->current : null;
        $userRef = $check->input->isUserAssigned() ? $check->input->getUser() : $current?->getUser();
        $providerRef = $check->input->isLoginProviderAssigned() ? $check->input->getLoginProvider() : $current?->getLoginProvider();
        if ($userRef === null || $providerRef === null) {
            // Both are required: the entity validation reports what is missing.
            return $check;
        }
        $user = $this->users->findOneByUid($userRef->uid() ?? '');
        $provider = $this->providers->findOneByUid($providerRef->uid() ?? '');
        if ($user === null || $provider === null || $user->getTenant()->uid() !== $provider->getTenant()->uid()) {
            $check->reject(new ConstraintFail('different-tenant', ['loginProvider'], [$providerRef->uid()]));
        }
        return $check;
    }
}
