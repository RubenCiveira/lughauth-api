<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Consent\Application\Usecase\ConsentOrchestration;

use Civi\Lughauth\Features\OAuth\Consent\Domain\PendingConsentRequest;

/**
 * Immutable value object returned by ConsentOrchestrationService after each queue inspection
 * or advancement step.
 *
 * When next is non-null the authorization flow must redirect the user to the consent screen for
 * that specific client. The remaining count tells the UI how many more consent items are waiting
 * so it can render an appropriate progress indicator.
 *
 * hasPending() is the idiomatic check used by callers to decide whether to continue with
 * token issuance or to interrupt the flow for consent collection.
 */
final class NextConsentResult
{
    public function __construct(
        /** The next PendingConsentRequest awaiting user action, or null when the queue is empty. */
        public readonly ?PendingConsentRequest $next,
        /** Total number of pending consent items still in the queue for the user, including next. */
        public readonly int $remaining,
    ) {
    }

    /**
     * Returns true when there is at least one pending consent item that requires user action
     * before the authorization flow can proceed to token issuance.
     */
    public function hasPending(): bool
    {
        return $this->next !== null;
    }
}
