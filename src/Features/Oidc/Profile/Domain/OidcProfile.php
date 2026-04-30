<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\Oidc\Profile\Domain;

class OidcProfile
{
    public function __construct(
        public readonly string $uid,
        public readonly string $userUid,
        public readonly ?string $givenName,
        public readonly ?string $familyName,
        public readonly ?string $middleName,
        public readonly ?string $nickname,
        public readonly ?string $preferredUsername,
        public readonly ?string $pictureUrl,
        public readonly ?string $websiteUrl,
        public readonly ?string $gender,
        public readonly ?string $birthdate,
        public readonly ?string $zoneinfo,
        public readonly ?string $locale,
        public readonly ?string $phoneNumber,
        public readonly ?bool $phoneNumberVerified,
        public readonly ?string $addressJson,
        public readonly ?string $updatedAt,
        public readonly ?int $version,
    ) {
    }
}
