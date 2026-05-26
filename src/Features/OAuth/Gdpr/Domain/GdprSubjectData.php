<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Domain;

/**
 * Immutable value object holding a labelled slice of personal data from one bounded context.
 *
 * Each GdprDataExportGateway contributor produces one or more instances of this class to
 * describe the personal data it manages. The context field identifies the originating domain
 * (e.g. "profile", "sessions", "tokens") so data subjects can understand where each piece
 * of information comes from. The data field is an arbitrary associative array whose structure
 * is defined by the contributing context; it is merged into the GdprDataPackage export map
 * keyed by context. Aggregated into a GdprDataPackage by ExportUserDataUsecase.
 */
final class GdprSubjectData
{
    public function __construct(
        /** Identifies the bounded context or data category that contributed this slice (e.g. "profile"). */
        public readonly string $context,
        /** Arbitrary associative array of personal-data fields as defined by the contributing context. */
        public readonly array $data,
    ) {
    }
}
