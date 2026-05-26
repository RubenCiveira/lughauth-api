<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;

/**
 * Exception thrown when the user must approve the OAuth scopes requested by the client.
 *
 * Raised during the authorization flow when the client requests scopes that have not yet
 * been explicitly consented to by the authenticated user for this particular client. This is
 * distinct from the general ConsentRequiredException, which covers terms-of-service or GDPR
 * acceptance; this exception is specifically about per-client scope approval.
 *
 * Caught by the authorization flow controller to redirect the user to the scope-consent step
 * rather than immediately issuing tokens. The embedded ChallengesState preserves the progress
 * made in the current flow so the user does not need to re-authenticate after consenting.
 */
final class ClientScopeConsentRequiredException extends LoginException
{
    /**
     * Creates a ready-to-throw instance with a scope-consent AuthenticationResult and a descriptive message.
     * Pass the current ChallengesState so the flow controller can resume from where it left off after consent.
     */
    public static function create(?ChallengesState $challenges = null): self
    {
        return new self(
            auth: AuthenticationResult::scopesConsentRequired(),
            message: 'Scope consent from the client is required before issuing tokens.',
            code: 401,
            previous: null,
            challenges: $challenges,
        );
    }
}
