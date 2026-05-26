<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;

/**
 * Exception thrown when no account can be found for the supplied identifier.
 *
 * Raised during the authentication flow when a lookup by username, email, or subject ID
 * returns no matching account in the identity store. To prevent user enumeration attacks,
 * callers should consider presenting the same error message to the end user as they would
 * for a WrongCredentialsException. Internally the distinction is preserved so that audit
 * logs and monitoring can differentiate between "account not found" and "wrong password".
 */
final class UnknownUserException extends LoginException
{
    /**
     * Creates a ready-to-throw instance for the given tenant and username combination.
     * Pass the current ChallengesState if flow context needs to be preserved for the error handler.
     */
    public static function create(string $tenant = '', string $username = '', ?ChallengesState $challenges = null): self
    {
        return new self(
            auth: AuthenticationResult::unknowUser($tenant, $username),
            message: 'No account found for the provided identifier.',
            code: 401,
            previous: null,
            challenges: $challenges,
        );
    }
}
