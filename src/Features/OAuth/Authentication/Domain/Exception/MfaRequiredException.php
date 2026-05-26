<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;

/**
 * Exception thrown when the user must complete a multi-factor authentication challenge.
 *
 * Raised during the authorization flow when the tenant or client policy requires MFA and the
 * user has already registered a second factor but has not yet verified it in the current session.
 * Distinct from NewMfaRequiredException, which is raised when no factor has been enrolled yet.
 *
 * The embedded ChallengesState preserves flow progress so that the MFA step handler can
 * resume the authorization without requiring the user to restart the login sequence.
 */
final class MfaRequiredException extends LoginException
{
    /**
     * Creates a ready-to-throw instance with an MFA-required AuthenticationResult and a standard message.
     * Pass the current ChallengesState to allow the flow controller to resume after the challenge is satisfied.
     */
    public static function create(?ChallengesState $challenges = null): self
    {
        return new self(
            auth: AuthenticationResult::mfaRequired(),
            message: 'Multi-factor authentication is required.',
            code: 401,
            previous: null,
            challenges: $challenges,
        );
    }
}
