<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;

/**
 * Exception thrown when the supplied credentials do not match any account.
 *
 * Raised during password-based authentication when the identity store rejects the provided
 * password, when the account is locked after repeated failures, or in any other scenario where
 * the credential check fails but the account identity itself is not the issue. This exception
 * is also used deliberately when an account is not found, so that the same error surface is
 * presented to the end user regardless of whether the identifier was valid, preventing enumeration.
 */
final class WrongCredentialsException extends LoginException
{
    /**
     * Creates a ready-to-throw instance for the given tenant and username combination.
     * Pass the current ChallengesState if flow context needs to be preserved for the error handler.
     */
    public static function create(string $tenant = '', string $username = '', ?ChallengesState $challenges = null): self
    {
        return new self(
            auth: AuthenticationResult::wrongCredentials($tenant, $username),
            message: 'The provided credentials are invalid.',
            code: 401,
            previous: null,
            challenges: $challenges,
        );
    }
}
