<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain;

use DateInterval;

/**
 * Lifetimes of the tokens a tenant issues, in one place for every grant and flow.
 *
 * `refresh` is the lifetime of each refresh token and `refreshFamily` the absolute lifetime of a
 * refresh family, counted from the original grant: no rotated refresh token outlives it. Both come
 * from the tenant configuration; access and ID tokens keep a short fixed lifetime (client
 * credentials use the client's own m2m TTL instead).
 */
final class TokenLifetimes
{
    public const int ACCESS_SECONDS = 600;
    public const int ID_SECONDS = 600;
    public const int REFRESH_SECONDS = 36000;
    public const int REFRESH_FAMILY_SECONDS = 2592000;

    public readonly DateInterval $access;
    public readonly DateInterval $id;
    public readonly DateInterval $refresh;
    public readonly DateInterval $refreshFamily;

    /** Non-positive values fall back to the defaults, as a tenant without configuration does. */
    public function __construct(
        int $accessSeconds = self::ACCESS_SECONDS,
        int $idSeconds = self::ID_SECONDS,
        int $refreshSeconds = self::REFRESH_SECONDS,
        int $refreshFamilySeconds = self::REFRESH_FAMILY_SECONDS,
    ) {
        $this->access = self::seconds($accessSeconds, self::ACCESS_SECONDS);
        $this->id = self::seconds($idSeconds, self::ID_SECONDS);
        $this->refresh = self::seconds($refreshSeconds, self::REFRESH_SECONDS);
        $this->refreshFamily = self::seconds($refreshFamilySeconds, self::REFRESH_FAMILY_SECONDS);
    }

    private static function seconds(int $value, int $default): DateInterval
    {
        return new DateInterval('PT' . ($value > 0 ? $value : $default) . 'S');
    }
}
