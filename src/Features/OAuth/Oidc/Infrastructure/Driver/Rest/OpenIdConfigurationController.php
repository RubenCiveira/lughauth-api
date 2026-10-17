<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Oidc\Infrastructure\Driver\Rest;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Civi\Lughauth\Shared\Context;
use Civi\Lughauth\Features\OAuth\Oidc\Domain\OidcCapabilities;
use Civi\Lughauth\Features\OAuth\Oidc\Domain\OpenIdConfiguration;
use Civi\Lughauth\Features\OAuth\Oidc\Domain\Gateway\DiscoveryContributorGateway;
use OpenApi\Attributes as OA;

/**
 * REST controller that serves the OIDC Discovery Document endpoint (OpenID Connect Discovery 1.0 / RFC 8414).
 *
 * Exposes GET /oauth/openid/{tenant}/.well-known/openid-configuration and returns a JSON object
 * containing the OpenID Provider Metadata for the requested tenant. The configuration is built
 * on each request via OpenIdConfiguration::forIssuer() from the canonical tenant issuer and the
 * implemented OidcCapabilities, then enriched by the registered DiscoveryContributorGateway
 * instances (which may only add fields, never override base ones). Clients (relying parties, SDKs, and CLI
 * tools) use this document to discover the issuer, authorization endpoint, token endpoint, JWKS
 * URI, and supported capabilities without requiring static configuration. The response is served
 * with a JSON Content-Type header as required by the specification.
 */
class OpenIdConfigurationController
{
    /** @param DiscoveryContributorGateway[] $contributors */
    public function __construct(
        private readonly Context $context,
        private readonly array $contributors = [],
    ) {
    }

    #[OA\Get(
        path: '/oauth/openid/{tenant}/.well-known/openid-configuration',
        summary: 'OpenID Connect Discovery Document',
        description: 'Returns the OpenID Provider Metadata as defined in RFC 8414 and OpenID Connect Discovery 1.0.',
        tags: ['OIDC'],
        parameters: [
            new OA\Parameter(name: 'tenant', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OpenID Provider Metadata'),
        ]
    )]
    /**
     * Builds the tenant-scoped OpenIdConfiguration, applies the contributors and serialises it as a JSON response.
     * A contributor that tries to redefine an existing field is a wiring error and fails loudly.
     */
    public function get(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tenant = $args['tenant'];
        $config = OpenIdConfiguration::forIssuer($this->context->getIssuer($tenant), OidcCapabilities::implemented());

        $document = $config->toArray();
        foreach ($this->contributors as $contributor) {
            $extra = $contributor->contribute($config, $tenant);
            $collisions = array_keys(array_intersect_key($extra, $document));
            if ($collisions !== []) {
                throw new \LogicException($contributor::class . ' redefines discovery fields: ' . implode(', ', $collisions));
            }
            $document += $extra;
        }

        $encoded = json_encode($document);
        $response->getBody()->write($encoded !== false ? $encoded : '{}');
        return $response->withHeader('Content-Type', 'application/json');
    }
}
