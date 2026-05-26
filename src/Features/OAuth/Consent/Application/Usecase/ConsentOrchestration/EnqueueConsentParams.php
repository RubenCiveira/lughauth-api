<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Consent\Application\Usecase\ConsentOrchestration;

/**
 * Immutable value object that carries the input parameters for the ConsentOrchestrationService
 * enqueue operation.
 *
 * This DTO bundles all context required to determine whether a scope consent item must be added
 * to the consent queue for a given user and client. The scope string follows the standard OAuth
 * space-separated format and is split by the service before querying the gateway.
 *
 * It is constructed by the authorization flow before calling ConsentOrchestrationService::enqueue
 * and does not carry any state beyond the raw request context.
 */
final class EnqueueConsentParams
{
    public function __construct(
        /** The subject identifier of the authenticated user for whom consent is being evaluated. */
        public readonly string $userId,
        /** The tenant name used to scope the consent lookup to the correct organizational context. */
        public readonly string $tenant,
        /** The OAuth client identifier requesting the scopes. */
        public readonly string $clientId,
        /** The redirect URI that the authorization server will use after the consent step completes. */
        public readonly string $redirectUri,
        /** The space-separated set of OAuth scopes being requested by the client. */
        public readonly string $scope,
    ) {
    }
}
