<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Consent\Domain\Gateway;

use Civi\Lughauth\Features\OAuth\Consent\Domain\PendingConsentRequest;

/**
 * Domain port (outbound gateway) that manages the persistence of the consent queue used
 * during the authorization flow.
 *
 * The consent queue holds one PendingConsentRequest per client whose scopes have not yet
 * been approved by the user. Implementations must preserve insertion order so that the
 * orchestration service can present consent screens in a deterministic sequence.
 *
 * The remove method is called after the user has approved (or denied) all scopes for a
 * specific client, allowing the queue to advance to the next pending item.
 */
interface ConsentQueueGateway
{
    /**
     * Adds the given consent request to the back of the queue for the user identified within
     * the request; replaces any existing entry for the same user/client combination.
     */
    public function enqueue(PendingConsentRequest $request): void;

    /**
     * Removes and returns the next pending consent request for the user, or null when the
     * queue is empty.
     */
    public function dequeueNext(string $userId, string $tenant): ?PendingConsentRequest;

    /**
     * Returns all pending consent requests for the user in the order they were enqueued.
     *
     * @return PendingConsentRequest[]
     */
    public function listPending(string $userId, string $tenant): array;

    /**
     * Removes the consent queue entry for the given user and client after the consent step
     * has been completed, freeing the slot for potential future re-consent.
     */
    public function remove(string $userId, string $tenant, string $clientId): void;
}
