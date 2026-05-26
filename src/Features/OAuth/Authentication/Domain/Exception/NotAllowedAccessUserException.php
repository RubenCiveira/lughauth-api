<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;

/**
 * Exception thrown when a valid user is denied access to a specific client or tenant.
 *
 * Raised when the authenticated user's identity is confirmed but an access-control policy
 * prevents them from using the requested client — for example, when the client restricts
 * login to a specific group or role that the user does not belong to. The HTTP status code
 * is 403 (Forbidden) rather than 401 to distinguish an authorization failure from an
 * authentication failure. Callers should log this event for security auditing purposes.
 */
final class NotAllowedAccessUserException extends LoginException
{
    /**
     * Creates a ready-to-throw instance identifying the denied tenant and username.
     * Pass the current ChallengesState if the flow needs to carry context for error-page rendering.
     */
    public static function create(string $tenant = '', string $username = '', ?ChallengesState $challenges = null): self
    {
        return new self(
            auth: AuthenticationResult::notAllowedAccess($tenant, $username),
            message: 'The user is not allowed to access this client.',
            code: 403,
            previous: null,
            challenges: $challenges,
        );
    }
}
