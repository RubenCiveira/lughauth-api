<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Domain\Gateway;

use Civi\Lughauth\Features\OAuth\Gdpr\Domain\GdprSubjectData;

/**
 * Outbound port for collecting personal data belonging to a subject for export (GDPR Art. 15).
 *
 * Each implementation represents one data-processing context (e.g. "profile", "sessions",
 * "tokens") and returns a slice of personal data as a GdprSubjectData instance. The
 * GdprDataAdapter composite aggregates results from all registered contributors into a
 * single flat list that the ExportUserDataUsecase assembles into a GdprDataPackage. Adding
 * a new data category only requires registering an additional implementation of this interface;
 * no changes to the application layer or other contributors are required.
 */
interface GdprDataExportGateway
{
    /**
     * Collects all personal data records for the subject within the given tenant from this contributor.
     * Returns one GdprSubjectData per logical data category handled by this implementation.
     *
     * @return GdprSubjectData[]
     */
    public function exportForSubject(string $subjectId, string $tenant): array;
}
