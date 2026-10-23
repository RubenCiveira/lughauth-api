<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Client\Domain;

/**
 * Which client authentication methods an endpoint accepts, besides private_key_jwt, which only a
 * client registered for it may use and which is accepted by every endpoint.
 */
enum ClientAuthPolicy
{
    /**
     * Only the method registered for the client (token_endpoint_auth_method), none included: the
     * authorization_code and refresh_token grants.
     */
    case RegisteredMethod;
    /**
     * client_secret_basic or client_secret_post, whichever the client registered: the other
     * token endpoint grants, PAR and introspection.
     */
    case Secret;
    /**
     * As Secret, and also the client_id alone of a client allowed public access: revocation and
     * the device code grant.
     */
    case SecretOrPublic;
}
