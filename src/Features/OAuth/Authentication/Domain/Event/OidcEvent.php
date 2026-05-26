<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\Event;

/**
 * Base class for all OIDC-related domain events.
 *
 * Provides the common context shared by every event in the authentication bounded context:
 * the tenant in which the event occurred and the precise moment it was raised. All concrete
 * OIDC event classes extend this base to ensure a consistent structure for event listeners,
 * audit log writers, and event-sourcing projections.
 *
 * The occurredAt timestamp defaults to the current instant at construction time, so callers
 * do not need to supply it unless they require a specific timestamp (e.g., for replays).
 */
abstract class OidcEvent
{
    /**
     * Identifier of the tenant in which this event was raised.
     * Used by multi-tenant listeners to route the event to the correct data partition.
     */
    public readonly string $tenant;

    /**
     * Precise moment at which the event occurred, defaulting to the instant of construction.
     * Immutable to ensure that replayed or forwarded events preserve the original timestamp.
     */
    public readonly \DateTimeImmutable $occurredAt;

    public function __construct(
        string $tenant,
        \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
        $this->tenant = $tenant;
        $this->occurredAt = $occurredAt;
    }
}
