<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Gateway;

/**
 * Port that downloads the JSON Web Key Set a client publishes at its `jwks_uri`.
 *
 * The URI is client-supplied, so implementations must refuse anything that could reach
 * internal services (only HTTPS to public addresses), bound the time and size of the
 * download, and cache the result per client so /authorize does not hit the network on
 * every request.
 */
interface RemoteJwksFetcher
{
    /**
     * Returns the decoded JWKS document (an array with a "keys" list).
     *
     * @throws \InvalidArgumentException when the URI is not allowed or the download fails or
     *                                   does not hold a JSON object.
     */
    public function fetch(string $tenant, string $clientId, string $jwksUri): array;
}
