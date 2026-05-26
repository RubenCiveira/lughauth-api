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

/**
 * REST controller that handles the GDPR Right of Access / Data Export endpoint (GDPR Article 15).
 *
 * Exposes GET /api/me/gdpr/export, which allows an authenticated user to download all personal
 * data held for their identity within the current tenant as a JSON attachment. The controller
 * reads the authenticated subject identity from the shared Context, rejects anonymous requests
 * with an UnauthorizedException, and delegates data collection to ExportUserDataUsecase. The
 * resulting GdprDataPackage is serialised as a JSON document and returned with a
 * Content-Disposition: attachment header so browsers prompt a file download. GdprException
 * errors are returned as JSON error bodies with the appropriate HTTP status code.
 */
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
    /**
     * Validates the caller is authenticated, collects all personal-data sections via the use case,
     * and returns a JSON attachment containing the serialised GdprDataPackage.
     */
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
