<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Infrastructure\Driver\Rest;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use OpenApi\Attributes as OA;
use Civi\Lughauth\Shared\Context;
use Civi\Lughauth\Shared\Exception\UnauthorizedException;
use Civi\Lughauth\Features\OAuth\Gdpr\Application\Usecase\ExportUserData\ExportUserDataParams;
use Civi\Lughauth\Features\OAuth\Gdpr\Application\Usecase\ExportUserData\ExportUserDataUsecase;
use Civi\Lughauth\Features\OAuth\Gdpr\Domain\Exception\GdprException;

class GdprExportController
{
    public function __construct(
        private readonly Context $context,
        private readonly ExportUserDataUsecase $usecase,
    ) {
    }

    #[OA\Get(
        path: '/api/me/gdpr/export',
        summary: 'Export personal data — GDPR Art. 15',
        description: 'Returns a JSON document containing all personal data held for the authenticated subject.',
        tags: ['GDPR'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Personal data export'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function export(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        try {
            return $this->handleExport($response);
        } catch (GdprException $ex) {
            $payload = ['error' => $ex->error(), 'error_description' => $ex->getMessage()];
            $encoded = json_encode($payload);
            $response->getBody()->write($encoded !== false ? $encoded : '{}');
            return $response->withStatus($ex->statusCode())->withHeader('Content-Type', 'application/json');
        }
    }

    private function handleExport(ResponseInterface $response): ResponseInterface
    {
        $identity = $this->context->getIdentity();
        if ($identity->anonymous || null === $identity->id) {
            throw new UnauthorizedException('Authentication required');
        }

        $result = $this->usecase->export(new ExportUserDataParams($identity->id, $identity->tenant ?? ''));

        $encoded = json_encode($result->package->toArray());
        $response->getBody()->write($encoded !== false ? $encoded : '{}');
        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Disposition', 'attachment; filename="gdpr-export.json"');
    }
}
