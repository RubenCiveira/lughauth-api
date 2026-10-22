<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\TokenSecurity\Domain;

/**
 * Purpose of a signed JWT, written as the JWS `typ` header.
 *
 * Every token this server signs carries one of these values so each consumer can reject a
 * token issued for another purpose (an ID token presented as an access token, an access token
 * presented as a refresh token, and so on), even when its signature and lifetime are valid.
 *
 * @see https://www.rfc-editor.org/rfc/rfc9068#section-2.1 (at+jwt)
 * @see https://openid.net/specs/openid-connect-backchannel-1_0.html#LogoutToken (logout+jwt)
 */
enum TokenType: string
{
    case Access = 'at+jwt';
    case Id = 'JWT';
    case Refresh = 'refresh+jwt';
    case Logout = 'logout+jwt';
    /** Internal token carrying login challenges between redirects; only `signKeypass` emits it. */
    case PreSession = 'presession+jwt';
    /** Internal token carrying a resolved PAR or JAR request between the steps of /authorize. */
    case AuthorizationRequest = 'authz-request+jwt';
}
