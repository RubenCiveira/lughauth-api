<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;

final class ConsentRequiredException extends LoginException
{
    public static function create(?ChallengesState $challenges = null): self
    {
        return new self(
            auth: AuthenticationResult::consentRequired(),
            message: 'Terms or GDPR consent is required before proceeding.',
            code: 401,
            previous: null,
            challenges: $challenges,
        );
    }
}
