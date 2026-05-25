<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;

final class NotAllowedAccessUserException extends LoginException
{
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
