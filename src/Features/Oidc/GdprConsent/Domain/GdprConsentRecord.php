<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\Oidc\GdprConsent\Domain;

final class GdprConsentRecord
{
    public function __construct(
        public readonly string $uid,
        public readonly string $purposeUid,
        public readonly string $purposeKey,
        public readonly string $title,
        public readonly string $description,
        public readonly bool $required,
        public readonly bool $granted,
        public readonly ?\DateTimeImmutable $decisionAt,
        public readonly ?string $ipAddress,
        public readonly ?string $userAgent,
    ) {
    }
}
