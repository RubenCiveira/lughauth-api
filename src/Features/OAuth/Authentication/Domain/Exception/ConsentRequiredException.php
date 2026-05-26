<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;

/**
 * Exception thrown when the user must accept terms-of-service or GDPR consent before proceeding.
 *
 * Raised during the authorization flow when the tenant or application requires explicit acceptance
 * of legal documents (e.g., terms of service, privacy policy) that the user has not yet agreed to.
 * This is distinct from ClientScopeConsentRequiredException, which is about OAuth scope approval.
 *
 * Caught by the authorization flow controller to redirect the user to the consent step. The embedded
 * ChallengesState preserves flow progress so the user resumes seamlessly after accepting the terms.
 */
final class ConsentRequiredException extends LoginException
{
    /**
     * Creates a ready-to-throw instance with a consent-required AuthenticationResult and a descriptive message.
     * Pass the current ChallengesState so the flow controller can resume from where it left off after the user accepts.
     */
    public static function create(?ChallengesState $challenges = null): self
    {
        return new self(
            auth: AuthenticationResult::consentRequired(),
            message: 'Terms or GDPR consent is required before proceeding.',
            code: 401,
            previous: null,
            challenges: $challenges,
        );
    }
}
