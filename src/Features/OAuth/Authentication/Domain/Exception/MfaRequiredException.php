<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;

final class MfaRequiredException extends LoginException
{
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
