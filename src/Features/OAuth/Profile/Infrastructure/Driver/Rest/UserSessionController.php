<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Profile\Infrastructure\Driver\Rest;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use OpenApi\Attributes as OA;
use Civi\Lughauth\Shared\Context;
use Civi\Lughauth\Shared\Exception\UnauthorizedException;
use Civi\Lughauth\Features\OAuth\Profile\Domain\Gateway\SessionsGateway;

/**
 * REST API controller that exposes session management endpoints for the authenticated user.
 *
 * Provides three operations under /api/me/sessions: listing all active sessions, revoking
 * a specific session by ID, and revoking all sessions at once (global logout).  Each
 * operation reads the caller's identity from the shared Context and rejects anonymous
 * requests with HTTP 401.
 *
 * Session data is sourced entirely from SessionsGateway, which abstracts the underlying
 * session store.  This controller contains no domain logic beyond identity verification
 * and response serialisation; all session-lifecycle concerns belong to the gateway layer.
 *
 * The revokeAll operation iterates the user's active sessions and revokes them one by one
 * through the same gateway, ensuring consistency with the single-session revocation path.
 */
class UserSessionController
{
    public function __construct(
        private readonly Context $context,
        private readonly SessionsGateway $sessions,
    ) {
    }

    /**
     * Handles GET /api/me/sessions — returns all active sessions for the authenticated user.
     *
     * Each session is serialised as a flat JSON object; the response array may be empty
     * when the user has no active sessions at the time of the call.
     */
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
        if ($identity->isAnonymous() || null === $identity->getId()) {
            throw new UnauthorizedException('Authentication required');
        }

        $sessions = $this->sessions->listByUser($identity->getId());

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

    /**
     * Handles DELETE /api/me/sessions/{sessionId} — revokes the specified session.
     *
     * After this call the session token associated with the given ID is permanently
     * invalidated and any subsequent refresh or introspection attempt will fail.
     */
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
        if ($identity->isAnonymous() || null === $identity->getId()) {
            throw new UnauthorizedException('Authentication required');
        }

        $this->sessions->revoke($args['sessionId'] ?? '');

        return $response->withStatus(204);
    }

    /**
     * Handles DELETE /api/me/sessions — revokes all active sessions (global logout).
     *
     * Iterates every active session for the authenticated user and revokes each one,
     * effectively signing the user out of all devices simultaneously.
     */
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
        if ($identity->isAnonymous() || null === $identity->getId()) {
            throw new UnauthorizedException('Authentication required');
        }

        foreach ($this->sessions->listByUser($identity->getId()) as $session) {
            $this->sessions->revoke($session->sessionId);
        }

        return $response->withStatus(204);
    }
}
