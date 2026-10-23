<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\DelegateLogin\Domain\Gateway;

/**
 * Links between a subject of a configured identity provider and the local account it signs in to.
 *
 * A link belongs to one provider record (its uid), not to a provider type: two SAML IdPs of the
 * same tenant never share it. Once linked, the subject reaches its account whatever email the
 * provider sends later.
 */
interface FederatedIdentityGateway
{
    /** The uid of the local user linked to the provider's subject, or null when there is none. */
    public function linkedUser(string $providerUid, string $subject): ?string;

    /** Whether the user has a link with the provider, for any subject. */
    public function isLinkedTo(string $userUid, string $providerUid): bool;

    /** Whether the user has a link with any provider. */
    public function hasLinks(string $userUid): bool;

    /**
     * Links the provider's subject to the user and returns the uid the subject is linked to.
     * An existing link of the subject is kept, so when a concurrent sign-in linked it first the
     * result is that other user, not the one given.
     */
    public function link(string $providerUid, string $subject, string $userUid): string;
}
