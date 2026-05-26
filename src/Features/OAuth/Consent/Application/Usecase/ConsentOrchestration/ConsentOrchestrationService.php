<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Consent\Application\Usecase\ConsentOrchestration;

use Civi\Lughauth\Features\OAuth\Consent\Domain\PendingConsentRequest;
use Civi\Lughauth\Features\OAuth\Consent\Domain\Gateway\ConsentQueueGateway;
use Civi\Lughauth\Features\OAuth\Consent\Domain\Gateway\ScopesConsentGateway;

/**
 * Domain service that orchestrates the multi-step scope consent flow during authorization.
 *
 * This service is the central coordinator for the consent queue: it decides whether a new
 * consent item needs to be enqueued by comparing the requested scopes against the user's
 * previously approved scopes via ScopesConsentGateway. If no new scopes need approval the
 * queue is left untouched and the authorization flow continues uninterrupted.
 *
 * It also provides the nextPending query used by the authorization endpoint to redirect the
 * user to the appropriate consent screen, and the completeAndAdvance operation that records
 * the user's approval decision and advances to the next pending item in the queue.
 */
final class ConsentOrchestrationService
{
    public function __construct(
        private readonly ConsentQueueGateway $queue,
        private readonly ScopesConsentGateway $scopes,
    ) {
    }

    /**
     * Checks whether any of the requested scopes are still pending consent and, if so,
     * pushes a new PendingConsentRequest onto the queue for the given user and client.
     */
    public function enqueue(EnqueueConsentParams $params): void
    {
        $pending = $this->scopes->pendingScopes(
            $params->tenant,
            $params->userId,
            $params->clientId,
            array_values(array_filter(explode(' ', $params->scope))),
        );

        if ($pending === []) {
            return;
        }

        $this->queue->enqueue(PendingConsentRequest::create(
            userId: $params->userId,
            tenant: $params->tenant,
            clientId: $params->clientId,
            redirectUri: $params->redirectUri,
            scope: $params->scope,
        ));
    }

    /**
     * Returns the next pending consent request for the given user and tenant along with the
     * total count of remaining items, or a result with no pending item when the queue is empty.
     */
    public function nextPending(string $userId, string $tenant): NextConsentResult
    {
        $all = $this->queue->listPending($userId, $tenant);
        $next = $all[0] ?? null;

        return new NextConsentResult(
            next: $next,
            remaining: count($all),
        );
    }

    /**
     * Persists the approved scopes for the given client, removes the corresponding queue entry,
     * and returns the next pending consent result so the caller can redirect accordingly.
     */
    public function completeAndAdvance(string $userId, string $tenant, string $clientId, array $approvedScopes): NextConsentResult
    {
        $this->scopes->storeAcceptedScopes($tenant, $userId, $clientId, $approvedScopes);
        $this->queue->remove($userId, $tenant, $clientId);

        return $this->nextPending($userId, $tenant);
    }
}
