<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;

final class ClientScopeConsentRequiredException extends LoginException
{
    public static function create(?ChallengesState $challenges = null): self
    {
        return new self(
            auth: AuthenticationResult::scopesConsentRequired(),
            message: 'Scope consent from the client is required before issuing tokens.',
            code: 401,
            previous: null,
            challenges: $challenges,
        );
    }
}
