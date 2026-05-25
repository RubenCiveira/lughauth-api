<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Application\Usecase\ExportUserData;

final class ExportUserDataParams
{
    public function __construct(
        public readonly string $subjectId,
        public readonly string $tenant,
    ) {
    }
}
