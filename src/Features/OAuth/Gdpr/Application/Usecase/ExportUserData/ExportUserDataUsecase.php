<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Application\Usecase\ExportUserData;

use Civi\Lughauth\Features\OAuth\Gdpr\Domain\GdprDataPackage;
use Civi\Lughauth\Features\OAuth\Gdpr\Domain\Gateway\GdprDataExportGateway;

/**
 * Application use case that collects and packages all personal data for a given subject (GDPR Article 15 — Right of Access).
 *
 * This use case queries all registered GdprDataExportGateway contributors and assembles their
 * results into a single GdprDataPackage stamped with the export timestamp. The package is then
 * wrapped in an ExportUserDataResult for the REST layer to serialise into the downloadable JSON
 * attachment. Each bounded context contributes its own GdprSubjectData slice independently,
 * allowing new data categories to be added by registering additional gateway implementations
 * without modifying this use case.
 */
final class ExportUserDataUsecase
{
    public function __construct(
        private readonly GdprDataExportGateway $gateway,
    ) {
    }

    /**
     * Collects personal data sections from all registered export contributors for the subject in params,
     * assembles them into a timestamped GdprDataPackage, and returns it wrapped in an ExportUserDataResult.
     */
    public function export(ExportUserDataParams $params): ExportUserDataResult
    {
        $sections = $this->gateway->exportForSubject($params->subjectId, $params->tenant);

        $package = new GdprDataPackage(
            subjectId: $params->subjectId,
            tenant: $params->tenant,
            exportedAt: new \DateTimeImmutable(),
            sections: $sections,
        );

        return new ExportUserDataResult($package);
    }
}
