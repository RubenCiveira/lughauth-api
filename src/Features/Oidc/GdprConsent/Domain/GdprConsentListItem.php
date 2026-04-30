<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\Oidc\GdprConsent\Domain;

final class GdprConsentListItem
{
    public function __construct(
        public readonly string $purposeUid,
        public readonly string $purposeKey,
        public readonly string $title,
        public readonly string $description,
        public readonly bool $required,
        public readonly ?bool $granted,
        public readonly ?\DateTimeImmutable $decisionAt,
    ) {
    }
}
