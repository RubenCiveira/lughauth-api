<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Domain;

/**
 * A slice of personal data belonging to one data-processing context (e.g. "profile", "sessions").
 *
 * Aggregated into a `GdprDataPackage` by `ExportUserDataUsecase`. The `context`
 * field identifies the source so data subjects can understand where each piece
 * of information originates.
 */
final class GdprSubjectData
{
    public function __construct(
        public readonly string $context,
        public readonly array $data,
    ) {
    }
}
