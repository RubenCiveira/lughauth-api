<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\TokenSecurity\Domain;

/**
 * Use of a one-time JWT whose `jti` is recorded by ReplayJtiGateway. The same `jti` may be
 * used once per purpose, so a request object never collides with a client assertion.
 * Values match the `_oauth_replay_jti.purpose` column.
 */
enum ReplayPurpose: string
{
    case ClientAssertion = 'client_assertion';
    case RequestObject = 'request_object';
    case Dpop = 'dpop';
    case JwtBearer = 'jwt_bearer';
    /** A request_ref whose authorization was completed: it cannot issue a second code. */
    case AuthorizationRequest = 'authorization_request';
    /** The binding of a delegated login to its browser: one callback finishes the login, once. */
    case DelegatedLogin = 'delegated_login';
}
