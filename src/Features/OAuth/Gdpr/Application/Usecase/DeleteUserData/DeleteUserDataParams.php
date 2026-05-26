<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Application\Usecase\DeleteUserData;

/**
 * Immutable input DTO for the DeleteUserDataUsecase carrying the identity of the data subject to erase.
 *
 * Passed by the REST controller after confirming the caller is authenticated. The subjectId
 * corresponds to the OAuth sub claim that uniquely identifies the user within the system.
 * The tenant field scopes the deletion to the correct tenant data partition so that a subject
 * identifier cannot accidentally trigger erasure across multiple tenants.
 */
final class DeleteUserDataParams
{
    /**
     * The OAuth sub claim that uniquely identifies the data subject whose records should be erased.
     * Must match the identity confirmed by the caller's authentication token.
     */
    public readonly string $subjectId;

    /**
     * The tenant identifier scoping the deletion to a specific data partition.
     * Prevents cross-tenant data erasure even when subject IDs collide across tenants.
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
