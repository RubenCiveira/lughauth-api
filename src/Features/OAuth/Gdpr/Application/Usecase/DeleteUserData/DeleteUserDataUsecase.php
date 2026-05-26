<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Application\Usecase\DeleteUserData;

use Civi\Lughauth\Features\OAuth\Gdpr\Domain\Gateway\GdprDataDeleteGateway;

/**
 * Application use case that permanently erases all personal data for a given subject (GDPR Article 17 — Right to Erasure).
 *
 * This use case is the single authorised entry point for irreversible data deletion. It
 * delegates to GdprDataDeleteGateway, which fans the deletion out to all registered
 * sub-context contributors so that every bounded context (sessions, tokens, profile, etc.)
 * participates in the erasure without coupling the application layer to storage details.
 * The controller is responsible for verifying the caller's identity before invoking this
 * use case; no additional authorization checks are performed here.
 */
final class DeleteUserDataUsecase
{
    public function __construct(
        private readonly GdprDataDeleteGateway $gateway,
    ) {
    }

    /**
     * Permanently deletes all personal data held for the subject identified in params.
     * Delegates to every registered GdprDataDeleteGateway contributor; the operation is irreversible.
     */
    public function delete(DeleteUserDataParams $params): void
    {
        $this->gateway->deleteForSubject($params->subjectId, $params->tenant);
    }
}
