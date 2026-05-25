<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Domain\Gateway;

interface GdprDataDeleteGateway
{
    public function deleteForSubject(string $subjectId, string $tenant): void;
}
