<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\Oidc\Profile\Domain\Gateway;

use Civi\Lughauth\Features\Oidc\Profile\Domain\OidcProfile;
use Civi\Lughauth\Features\Oidc\Profile\Domain\OidcProfileData;

interface ProfileGateway
{
    public function findByUser(string $userUid): ?OidcProfile;

    public function save(string $userUid, OidcProfileData $data): OidcProfile;
}
