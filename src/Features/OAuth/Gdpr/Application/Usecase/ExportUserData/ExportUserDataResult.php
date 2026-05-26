<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Application\Usecase\ExportUserData;

use Civi\Lughauth\Features\OAuth\Gdpr\Domain\GdprDataPackage;

/**
 * Output DTO returned by ExportUserDataUsecase after collecting all personal data for a subject.
 *
 * Wraps a fully-assembled GdprDataPackage that contains the subject identifier, tenant,
 * export timestamp, and the list of GdprSubjectData sections contributed by each data-context
 * adapter. The REST controller serialises this result via GdprDataPackage::toArray() into the
 * JSON attachment sent in the export response.
 */
final class ExportUserDataResult
{
    /**
     * The assembled personal-data package ready for serialisation and delivery to the data subject.
     * Contains all sections collected from every registered export contributor.
     */
    public readonly GdprDataPackage $package;

    public function __construct(
        GdprDataPackage $package,
    ) {
        $this->package = $package;
    }
}
