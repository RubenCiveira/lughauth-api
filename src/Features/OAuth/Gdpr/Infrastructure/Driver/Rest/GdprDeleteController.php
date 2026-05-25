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
