<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Application\Usecase\ExportUserData;

/**
 * Immutable input DTO for the ExportUserDataUsecase identifying the subject whose data should be exported.
 *
 * Passed by the REST controller after authenticating the caller. The subjectId maps to the OAuth
 * sub claim and the tenant scopes the query to the correct data partition, mirroring the same
 * design as DeleteUserDataParams to ensure consistent subject scoping across GDPR operations.
 */
final class ExportUserDataParams
{
    /**
     * The OAuth sub claim uniquely identifying the data subject whose personal data is to be exported.
     */
    public readonly string $subjectId;

    /**
     * The tenant identifier scoping the export query to the correct data partition.
     */
    public readonly string $tenant;

    public function __construct(
        string $subjectId,
        string $tenant,
    ) {
        $this->subjectId = $subjectId;
        $this->tenant = $tenant;
    }
}
