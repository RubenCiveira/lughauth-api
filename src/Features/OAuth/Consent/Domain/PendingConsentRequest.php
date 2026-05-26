<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Consent\Domain;

/**
 * Immutable value object representing a queued consent item awaiting user action during the
 * OAuth authorization flow.
 *
 * An instance is created by ConsentOrchestrationService::enqueue whenever it detects that
 * the user has not yet approved all requested scopes for a given client. The object is
 * persisted via ConsentQueueGateway and later retrieved by the authorization controller to
 * redirect the user to the appropriate consent screen.
 *
 * The createdAt timestamp is set to the current wall-clock time at construction so that
 * implementations can enforce queue TTLs or display "waiting since" information. scopeList()
 * returns the individual scope identifiers so the consent UI can render one checkbox per scope.
 */
final class PendingConsentRequest
{
    public function __construct(
        /** The subject identifier of the user for whom consent is pending. */
        public readonly string $userId,
        /** The tenant context in which the authorization request was initiated. */
        public readonly string $tenant,
        /** The OAuth client identifier whose requested scopes need user approval. */
        public readonly string $clientId,
        /** The redirect URI to return to after the consent step completes. */
        public readonly string $redirectUri,
        /** Space-separated OAuth scope string listing all scopes that require consent. */
        public readonly string $scope,
        /** The wall-clock timestamp at which this consent request was enqueued. */
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Named constructor that creates a new instance with createdAt set to the current time,
     * providing a convenient factory for use cases that do not need to supply a custom timestamp.
     */
    public static function create(
        string $userId,
        string $tenant,
        string $clientId,
        string $redirectUri,
        string $scope,
    ): self {
        return new self(
            userId: $userId,
            tenant: $tenant,
            clientId: $clientId,
            redirectUri: $redirectUri,
            scope: $scope,
            createdAt: new \DateTimeImmutable(),
        );
    }

    /**
     * Splits the space-separated scope string into an indexed array of individual scope
     * identifiers, filtering out any empty segments.
     */
    public function scopeList(): array
    {
        return array_values(array_filter(explode(' ', $this->scope)));
    }
}
