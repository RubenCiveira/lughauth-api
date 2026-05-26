<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Oidc\Infrastructure\Driver\Rest;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Civi\Lughauth\Shared\Context;
use Civi\Lughauth\Features\OAuth\Oidc\Domain\OpenIdConfiguration;
use OpenApi\Attributes as OA;

/**
 * REST controller that serves the OIDC Discovery Document endpoint (OpenID Connect Discovery 1.0 / RFC 8414).
 *
 * Exposes GET /oauth/openid/{tenant}/.well-known/openid-configuration and returns a JSON object
 * containing the OpenID Provider Metadata for the requested tenant. The configuration is built
 * on each request via OpenIdConfiguration::forTenant(), which derives all endpoint URLs from
 * the application base URL and the tenant path segment. Clients (relying parties, SDKs, and CLI
 * tools) use this document to discover the issuer, authorization endpoint, token endpoint, JWKS
 * URI, and supported capabilities without requiring static configuration. The response is served
 * with a JSON Content-Type header as required by the specification.
 */
class OpenIdConfigurationController
{
    public function __construct(private readonly Context $context)
    {
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
     * Builds the tenant-scoped OpenIdConfiguration and serialises it as a JSON response.
     * All endpoint URLs are derived at request time from the application base URL and the tenant argument.
     */
    public function get(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $config = OpenIdConfiguration::forTenant(
            baseOAuthUrl: $this->context->getBaseUrl() . '/oauth',
            tenant: $args['tenant'],
        );

        $encoded = json_encode($config->toArray());
        $response->getBody()->write($encoded !== false ? $encoded : '{}');
        return $response->withHeader('Content-Type', 'application/json');
    }
}
