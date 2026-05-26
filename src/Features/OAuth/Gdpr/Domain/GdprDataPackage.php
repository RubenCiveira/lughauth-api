<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Domain;

/**
 * Immutable value object that represents the full personal-data export package for a subject.
 *
 * Assembled by ExportUserDataUsecase by collecting GdprSubjectData slices from every registered
 * GdprDataExportGateway contributor. The exportedAt timestamp records when the export was
 * generated so the data subject can verify its freshness. The toArray() method produces a
 * JSON-serialisable structure following a predictable schema: subject_id, tenant, exported_at
 * (ISO 8601), and a data map keyed by each contributor's context name. The REST controller
 * delivers this structure as a downloadable JSON attachment.
 *
 * @param GdprSubjectData[] $sections
 */
final class GdprDataPackage
{
    /** @param GdprSubjectData[] $sections */
    public function __construct(
        /** The OAuth sub claim identifying the data subject to whom this package belongs. */
        public readonly string $subjectId,
        /** The tenant identifier scoping this package to a specific data partition. */
        public readonly string $tenant,
        /** The instant at which this export was generated, included for freshness auditability. */
        public readonly \DateTimeImmutable $exportedAt,
        /** Ordered list of personal-data slices, one per contributing data context. */
        public readonly array $sections,
    ) {
    }

    /**
     * Serialises the package to an associative array suitable for JSON encoding and delivery.
     * The data map is keyed by each GdprSubjectData context name for human readability.
     */
    public function toArray(): array
    {
        return [
            'subject_id' => $this->subjectId,
            'tenant' => $this->tenant,
            'exported_at' => $this->exportedAt->format(\DateTimeInterface::ATOM),
            'data' => array_reduce(
                $this->sections,
                static fn (array $carry, GdprSubjectData $s) => array_merge($carry, [$s->context => $s->data]),
                [],
            ),
        ];
    }
}
