<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Infrastructure\Driven;

use Civi\Lughauth\Features\OAuth\Gdpr\Domain\Gateway\GdprDataExportGateway;
use Civi\Lughauth\Features\OAuth\Gdpr\Domain\Gateway\GdprDataDeleteGateway;
use Civi\Lughauth\Features\OAuth\Gdpr\Domain\GdprSubjectData;
use Override;

/**
 * Composite adapter that aggregates GDPR data across registered sub-context contributors.
 * Register additional GdprDataExportGateway / GdprDataDeleteGateway per sub-context via DI.
 */
final class GdprDataAdapter implements GdprDataExportGateway, GdprDataDeleteGateway
{
    /** @param GdprDataExportGateway[] $exportContributors */
    /** @param GdprDataDeleteGateway[] $deleteContributors */
    public function __construct(
        private readonly array $exportContributors = [],
        private readonly array $deleteContributors = [],
    ) {
    }

    #[Override]
    /** @return GdprSubjectData[] */
    public function exportForSubject(string $subjectId, string $tenant): array
    {
        $sections = [];
        foreach ($this->exportContributors as $contributor) {
            array_push($sections, ...$contributor->exportForSubject($subjectId, $tenant));
        }
        return $sections;
    }

    #[Override]
    public function deleteForSubject(string $subjectId, string $tenant): void
    {
        foreach ($this->deleteContributors as $contributor) {
            $contributor->deleteForSubject($subjectId, $tenant);
        }
    }
}
