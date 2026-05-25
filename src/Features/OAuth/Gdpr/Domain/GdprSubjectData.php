<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Domain;

final class GdprSubjectData
{
    public function __construct(
        public readonly string $context,
        public readonly array $data,
    ) {
    }
}
