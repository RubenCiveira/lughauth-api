<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain;

/**
 * How the user of an in-progress authorization flow proved who they are.
 *
 * Only the step that checks that proof sets it on the ChallengesState: a username without it is
 * just what someone typed, so the later steps (MFA, consent, password change) must not trust it.
 */
enum IdentificationMethod: string
{
    /** The password was checked on the login step; MFA and a temporal password still apply. */
    case PASSWORD = 'pwd';

    /** A WebAuthn assertion was verified. */
    case WEBAUTHN = 'webauthn';

    /** A federated identity provider returned a valid identity. */
    case FEDERATED = 'federated';

    /** A password recovery code sent by email was redeemed. */
    case PASSWORD_RECOVERY = 'password-recovery';

    /** A self-registration verification code was redeemed. */
    case REGISTRATION = 'registration';

    /** A valid server-side session of the user already exists. */
    case SESSION = 'session';
}
