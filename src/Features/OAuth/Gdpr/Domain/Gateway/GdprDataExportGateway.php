<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Domain\Gateway;

use Civi\Lughauth\Features\OAuth\Gdpr\Domain\GdprSubjectData;

interface GdprDataExportGateway
{
    /** @return GdprSubjectData[] */
    public function exportForSubject(string $subjectId, string $tenant): array;
}
