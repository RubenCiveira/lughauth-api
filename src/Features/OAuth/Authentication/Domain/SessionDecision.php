<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain;

/**
 * What the authorization endpoint does with the browser session for a request
 * (SessionSufficiencyPolicy).
 */
enum SessionDecision
{
    /** The session satisfies the request: authorize from it without asking for credentials. */
    case Reuse;
    /** No usable session: show the login form. */
    case Authenticate;
    /** No usable session and prompt=none: fail with login_required without any UI. */
    case LoginRequired;
    /**
     * The session is fresh but below the acr the client asked for: keep the user and ask only for
     * the second factor (task 067).
     */
    case StepUp;
    /** Step-up needed with prompt=none: fail with interaction_required without any UI. */
    case InteractionRequired;
}
