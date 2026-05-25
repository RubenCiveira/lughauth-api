<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Application\Usecase\DeleteUserData;

use Civi\Lughauth\Features\OAuth\Gdpr\Domain\Gateway\GdprDataDeleteGateway;

final class DeleteUserDataUsecase
{
    public function __construct(
        private readonly GdprDataDeleteGateway $gateway,
    ) {
    }

    public function delete(DeleteUserDataParams $params): void
    {
        $this->gateway->deleteForSubject($params->subjectId, $params->tenant);
    }
}
