<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Domain;

final class GdprDataPackage
{
    /** @param GdprSubjectData[] $sections */
    public function __construct(
        public readonly string $subjectId,
        public readonly string $tenant,
        public readonly \DateTimeImmutable $exportedAt,
        public readonly array $sections,
    ) {
    }

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
