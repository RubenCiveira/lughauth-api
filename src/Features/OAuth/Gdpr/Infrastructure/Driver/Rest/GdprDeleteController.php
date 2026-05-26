<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Infrastructure\Driver\Rest;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use OpenApi\Attributes as OA;
use Civi\Lughauth\Shared\Context;
use Civi\Lughauth\Shared\Exception\UnauthorizedException;
use Civi\Lughauth\Features\OAuth\Gdpr\Application\Usecase\DeleteUserData\DeleteUserDataParams;
use Civi\Lughauth\Features\OAuth\Gdpr\Application\Usecase\DeleteUserData\DeleteUserDataUsecase;
use Civi\Lughauth\Features\OAuth\Gdpr\Domain\Exception\GdprException;

/**
 * REST controller that handles the GDPR Right to Erasure endpoint (GDPR Article 17).
 *
 * Exposes DELETE /api/me/gdpr/data, which allows an authenticated user to request the permanent
 * deletion of all personal data held for their identity within the current tenant. The controller
 * reads the authenticated subject identity from the shared Context, rejects anonymous requests
 * with an UnauthorizedException, and delegates the actual erasure to DeleteUserDataUsecase.
 * GdprException errors (e.g. subject not found, access denied) are serialised as JSON error
 * objects with the appropriate HTTP status. The operation is irreversible once executed.
 */
class GdprDeleteController
{
    public function __construct(
        private readonly Context $context,
        private readonly DeleteUserDataUsecase $usecase,
    ) {
    }

    #[OA\Delete(
        path: '/api/me/gdpr/data',
        summary: 'Delete personal data — GDPR Art. 17 (Right to Erasure)',
        description: 'Permanently deletes all personal data held for the authenticated subject. This action is irreversible.',
        tags: ['GDPR'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 204, description: 'Personal data deleted'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    /**
     * Validates that the caller is authenticated, then permanently erases all their personal data.
     * Returns 204 No Content on success or a JSON error body with the appropriate status code on failure.
     */
    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            return $this->handleDelete($response);
        } catch (GdprException $ex) {
            $payload = ['error' => $ex->error(), 'error_description' => $ex->getMessage()];
            $encoded = json_encode($payload);
            $response->getBody()->write($encoded !== false ? $encoded : '{}');
            return $response->withStatus($ex->statusCode())->withHeader('Content-Type', 'application/json');
        }
    }

    private function handleDelete(ResponseInterface $response): ResponseInterface
    {
        $identity = $this->context->getIdentity();
        if ($identity->anonymous || null === $identity->id) {
            throw new UnauthorizedException('Authentication required');
        }

        $this->usecase->delete(new DeleteUserDataParams($identity->id, $identity->tenant ?? ''));

        return $response->withStatus(204);
    }
}
