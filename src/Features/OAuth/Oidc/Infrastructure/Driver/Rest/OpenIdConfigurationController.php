<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Oidc\Infrastructure\Driver\Rest;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Civi\Lughauth\Shared\Context;
use Civi\Lughauth\Features\OAuth\Oidc\Domain\OpenIdConfiguration;
use OpenApi\Attributes as OA;

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
