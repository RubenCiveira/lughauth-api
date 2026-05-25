<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Application\Usecase\ExportUserData;

use Civi\Lughauth\Features\OAuth\Gdpr\Domain\GdprDataPackage;
use Civi\Lughauth\Features\OAuth\Gdpr\Domain\Gateway\GdprDataExportGateway;

final class ExportUserDataUsecase
{
    public function __construct(
        private readonly GdprDataExportGateway $gateway,
    ) {
    }

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
