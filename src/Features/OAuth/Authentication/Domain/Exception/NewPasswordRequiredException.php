<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;

final class NewPasswordRequiredException extends LoginException
{
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
