<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Consent\Application\Usecase\ConsentOrchestration;

use Civi\Lughauth\Features\OAuth\Consent\Domain\PendingConsentRequest;
use Civi\Lughauth\Features\OAuth\Consent\Domain\Gateway\ConsentQueueGateway;
use Civi\Lughauth\Features\OAuth\Consent\Domain\Gateway\ScopesConsentGateway;

final class ConsentOrchestrationService
{
    public function __construct(
        private readonly ConsentQueueGateway $queue,
        private readonly ScopesConsentGateway $scopes,
    ) {
    }

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

    public function nextPending(string $userId, string $tenant): NextConsentResult
    {
        $all = $this->queue->listPending($userId, $tenant);
        $next = $all[0] ?? null;

        return new NextConsentResult(
            next: $next,
            remaining: count($all),
        );
    }

    public function completeAndAdvance(string $userId, string $tenant, string $clientId, array $approvedScopes): NextConsentResult
    {
        $this->scopes->storeAcceptedScopes($tenant, $userId, $clientId, $approvedScopes);
        $this->queue->remove($userId, $tenant, $clientId);

        return $this->nextPending($userId, $tenant);
    }
}
