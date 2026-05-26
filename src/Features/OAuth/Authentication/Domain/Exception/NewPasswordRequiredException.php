<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;

/**
 * Exception thrown when the user must set a new password before the account can be used.
 *
 * Raised when the identity store determines that the current password has expired, that an
 * administrator has forced a reset, or that the account was provisioned with a temporary
 * password that must be changed on first use. The authorization flow pauses and redirects
 * the user to the password-change step, after which the original flow is resumed from the
 * preserved ChallengesState without requiring the user to restart authentication.
 */
final class NewPasswordRequiredException extends LoginException
{
    /**
     * Creates a ready-to-throw instance with a new-password-required AuthenticationResult and a standard message.
     * Pass the current ChallengesState so the flow controller can resume after the password is successfully changed.
     */
    public static function create(?ChallengesState $challenges = null): self
    {
        return new self(
            auth: AuthenticationResult::newPasswordRequired(),
            message: 'A password change is required before this account can be used.',
            code: 401,
            previous: null,
            challenges: $challenges,
        );
    }
}
