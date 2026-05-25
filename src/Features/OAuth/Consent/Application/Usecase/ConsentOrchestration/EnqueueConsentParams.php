<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Consent\Application\Usecase\ConsentOrchestration;

final class EnqueueConsentParams
{
    public function __construct(
        public readonly string $userId,
        public readonly string $tenant,
        public readonly string $clientId,
        public readonly string $redirectUri,
        public readonly string $scope,
    ) {
    }
}
