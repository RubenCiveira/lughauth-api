<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Application\Usecase\ExportUserData;

use Civi\Lughauth\Features\OAuth\Gdpr\Domain\GdprDataPackage;

final class ExportUserDataResult
{
    public function __construct(
        public readonly GdprDataPackage $package,
    ) {
    }
}
