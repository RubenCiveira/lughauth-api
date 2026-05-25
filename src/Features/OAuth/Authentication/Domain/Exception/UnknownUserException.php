<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;

final class UnknownUserException extends LoginException
{
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
