<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway;

use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\ReplayPurpose;

/**
 * Port for the shared anti-replay store of one-time JWTs (request objects, client assertions,
 * DPoP proofs and JWT bearer grants).
 *
 * A `jti` is unique per tenant, issuing client and purpose. Recording it must be atomic: when
 * two requests present the same `jti` at the same time, exactly one of them wins.
 */
interface ReplayJtiGateway
{
    /**
     * Records the `jti` until it expires and reports whether this call recorded it first.
     * False means the JWT was already used: a replay.
     */
    public function consume(string $tenant, string $clientId, ReplayPurpose $purpose, string $jti, \DateTimeImmutable $expiresAt): bool;

    /**
     * Forgets a `jti` consumed by a use that did not take effect (the operation it guarded
     * failed), so the same JWT can be presented again.
     */
    public function release(string $tenant, string $clientId, ReplayPurpose $purpose, string $jti): void;

    /**
     * Deletes the identifiers whose JWT has expired; an expired JWT is rejected on its own.
     */
    public function purgeExpired(): void;
}
