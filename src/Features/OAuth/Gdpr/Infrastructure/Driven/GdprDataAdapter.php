<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Infrastructure\Driven;

use Civi\Lughauth\Features\OAuth\Gdpr\Domain\Gateway\GdprDataExportGateway;
use Civi\Lughauth\Features\OAuth\Gdpr\Domain\Gateway\GdprDataDeleteGateway;
use Civi\Lughauth\Features\OAuth\Gdpr\Domain\GdprSubjectData;
use Override;

/**
 * Composite adapter that implements both GDPR gateway ports by delegating to contributor lists.
 *
 * This class follows the Composite design pattern: it holds injected lists of
 * GdprDataExportGateway and GdprDataDeleteGateway contributors and fans each operation out to
 * every registered contributor. New bounded contexts that hold personal data need only register
 * an additional contributor via dependency injection; the application use cases and this adapter
 * require no modification. Export contributors each return zero or more GdprSubjectData sections
 * which are merged into a flat list; delete contributors each erase their own data independently.
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
    /**
     * Iterates all registered export contributors, collects their GdprSubjectData sections,
     * and returns the merged flat list for the given subject and tenant.
     *
     * @return GdprSubjectData[]
     */
    public function exportForSubject(string $subjectId, string $tenant): array
    {
        $sections = [];
        foreach ($this->exportContributors as $contributor) {
            array_push($sections, ...$contributor->exportForSubject($subjectId, $tenant));
        }
        return $sections;
    }

    #[Override]
    /**
     * Iterates all registered delete contributors and instructs each to erase the subject's data.
     * The operation is irreversible and covers every bounded context that has a registered contributor.
     */
    public function deleteForSubject(string $subjectId, string $tenant): void
    {
        foreach ($this->deleteContributors as $contributor) {
            $contributor->deleteForSubject($subjectId, $tenant);
        }
    }
}
