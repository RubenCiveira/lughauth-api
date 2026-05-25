<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Consent\Domain\Gateway;

use Civi\Lughauth\Features\OAuth\Consent\Domain\PendingConsentRequest;

interface ConsentQueueGateway
{
    public function enqueue(PendingConsentRequest $request): void;

    public function dequeueNext(string $userId, string $tenant): ?PendingConsentRequest;

    /** @return PendingConsentRequest[] */
    public function listPending(string $userId, string $tenant): array;

    public function remove(string $userId, string $tenant, string $clientId): void;
}
