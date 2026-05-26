<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Oidc\Domain\Gateway;

use Civi\Lughauth\Features\OAuth\Oidc\Domain\OpenIdConfiguration;

/**
 * Outbound port for contributing additional fields to the OIDC Discovery Document.
 *
 * Each implementation adds a set of key-value pairs that will be merged into the JSON
 * body served at /.well-known/openid-configuration. Contributors allow optional capabilities
 * (such as mTLS endpoint aliases, backchannel logout URIs, or custom claims) to be appended
 * to the base document without modifying OpenIdConfiguration itself. The OpenIdConfigurationController
 * collects all registered contributors, calls contribute() on each, and deep-merges the results
 * onto the base document before serialising the response. Implementations receive the fully built
 * base configuration and the current tenant so they can make tenant-specific decisions.
 */
interface DiscoveryContributorGateway
{
    /**
     * Returns an associative array of fields to merge into the discovery document for the given tenant.
     * Keys must follow the OpenID Provider Metadata naming conventions (snake_case).
     */
    public function contribute(OpenIdConfiguration $base, string $tenant): array;
}
