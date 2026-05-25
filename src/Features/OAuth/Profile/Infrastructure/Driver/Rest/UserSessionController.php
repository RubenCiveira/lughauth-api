<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Profile\Infrastructure\Driver\Rest;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use OpenApi\Attributes as OA;
use Civi\Lughauth\Shared\Context;
use Civi\Lughauth\Shared\Exception\UnauthorizedException;
use Civi\Lughauth\Features\OAuth\Profile\Domain\Gateway\SessionsGateway;

class UserSessionController
{
    public function __construct(
        private readonly Context $context,
        private readonly SessionsGateway $sessions,
    ) {
    }

    #[OA\Get(
        path: '/api/me/sessions',
        summary: 'List active sessions',
        description: 'Returns all active OAuth sessions for the authenticated user.',
        tags: ['Profile'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Active sessions list'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function list(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $identity = $this->context->getIdentity();
        if ($identity->anonymous || null === $identity->id) {
            throw new UnauthorizedException('Authentication required');
        }

        $sessions = $this->sessions->listByUser($identity->id);

        $payload = array_map(static fn ($s) => [
            'session_id' => $s->sessionId,
            'client_id' => $s->clientId,
            'client_name' => $s->clientName,
            'ip_address' => $s->ipAddress,
            'user_agent' => $s->userAgent,
            'last_used_at' => $s->lastUsedAt,
            'expires_at' => $s->expiration,
        ], $sessions);

        $encoded = json_encode($payload);
        $response->getBody()->write($encoded !== false ? $encoded : '[]');
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }

    #[OA\Delete(
        path: '/api/me/sessions/{sessionId}',
        summary: 'Revoke a specific session',
        description: 'Revokes the given session, forcing re-authentication for that device.',
        tags: ['Profile'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'sessionId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Session revoked'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function revoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $identity = $this->context->getIdentity();
        if ($identity->anonymous || null === $identity->id) {
            throw new UnauthorizedException('Authentication required');
        }

        $this->sessions->revoke($args['sessionId'] ?? '');

        return $response->withStatus(204);
    }

    #[OA\Delete(
        path: '/api/me/sessions',
        summary: 'Revoke all sessions (global logout)',
        description: 'Revokes all active sessions for the authenticated user.',
        tags: ['Profile'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 204, description: 'All sessions revoked'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function revokeAll(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $identity = $this->context->getIdentity();
        if ($identity->anonymous || null === $identity->id) {
            throw new UnauthorizedException('Authentication required');
        }

        foreach ($this->sessions->listByUser($identity->id) as $session) {
            $this->sessions->revoke($session->sessionId);
        }

        return $response->withStatus(204);
    }
}
