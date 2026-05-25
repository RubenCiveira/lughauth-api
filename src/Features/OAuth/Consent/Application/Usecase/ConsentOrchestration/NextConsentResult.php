<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Consent\Application\Usecase\ConsentOrchestration;

use Civi\Lughauth\Features\OAuth\Consent\Domain\PendingConsentRequest;

final class NextConsentResult
{
    public function __construct(
        public readonly ?PendingConsentRequest $next,
        public readonly int $remaining,
    ) {
    }

    public function hasPending(): bool
    {
        return $this->next !== null;
    }
}
