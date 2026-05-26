<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;

/**
 * Exception thrown when the user must enrol in multi-factor authentication before the account is usable.
 *
 * Raised when the tenant or client policy mandates MFA and the authenticated user has not yet
 * registered any second factor. Unlike MfaRequiredException, which challenges an existing factor,
 * this exception redirects the user to the MFA enrolment step where they set up a new factor for
 * the first time. After successful enrolment the flow continues from the preserved ChallengesState.
 */
final class NewMfaRequiredException extends LoginException
{
    /**
     * Creates a ready-to-throw instance with a new-MFA-required AuthenticationResult and a standard message.
     * Pass the current ChallengesState to allow the flow controller to resume after enrolment completes.
     */
    public static function create(?ChallengesState $challenges = null): self
    {
        return new self(
            auth: AuthenticationResult::newMfaRequired(),
            message: 'MFA must be configured before the account can be used.',
            code: 401,
            previous: null,
            challenges: $challenges,
        );
    }
}
