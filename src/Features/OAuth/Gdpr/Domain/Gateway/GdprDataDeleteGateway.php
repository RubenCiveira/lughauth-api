<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Domain\Gateway;

/**
 * Outbound port for irreversible deletion of all personal data belonging to a subject (GDPR Art. 17).
 *
 * Implementations fan the deletion request out to every storage system that holds personal data
 * for the given subject within the specified tenant. The GdprDataAdapter composite registers
 * multiple contributor implementations to cover every bounded context (sessions, tokens, profile,
 * social-login linkages, etc.) without the application layer needing to know about individual
 * storage tables. The operation must be treated as best-effort and irreversible; callers should
 * confirm the subject's identity before invoking this port.
 */
interface GdprDataDeleteGateway
{
    /**
     * Permanently deletes all personal data records associated with the subject in the given tenant.
     * The operation is irreversible; all storage contributors are expected to participate.
     */
    public function deleteForSubject(string $subjectId, string $tenant): void;
}
