<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\Oidc\Profile\Infrastructure\Driver\Html\Panels;

use Civi\Lughauth\Features\Oidc\Profile\Domain\OidcProfile;

class ProfileEditPanel
{
    public function render(?OidcProfile $profile, string $saveUrl, string $cancelUrl, ?string $error = null): string
    {
        $saveUrl = htmlspecialchars($saveUrl);
        $cancelUrl = htmlspecialchars($cancelUrl);
        $errorHtml = $error !== null && $error !== ''
            ? '<p class="error">' . htmlspecialchars($error) . '</p>'
            : '';

        $v = static fn(?string $val): string => htmlspecialchars($val ?? '');

        return <<<HTML
            <h1>Edit profile</h1>
            {$errorHtml}
            <form method="POST" action="{$saveUrl}" class="profile-edit-form">
                <label>Given name
                    <input type="text" name="givenName" value="{$v($profile?->givenName)}" />
                </label>
                <label>Family name
                    <input type="text" name="familyName" value="{$v($profile?->familyName)}" />
                </label>
                <label>Middle name
                    <input type="text" name="middleName" value="{$v($profile?->middleName)}" />
                </label>
                <label>Nickname
                    <input type="text" name="nickname" value="{$v($profile?->nickname)}" />
                </label>
                <label>Preferred username
                    <input type="text" name="preferredUsername" value="{$v($profile?->preferredUsername)}" />
                </label>
                <label>Picture URL
                    <input type="url" name="pictureUrl" value="{$v($profile?->pictureUrl)}" />
                </label>
                <label>Website
                    <input type="url" name="websiteUrl" value="{$v($profile?->websiteUrl)}" />
                </label>
                <label>Gender
                    <input type="text" name="gender" value="{$v($profile?->gender)}" />
                </label>
                <label>Birthdate
                    <input type="date" name="birthdate" value="{$v($profile?->birthdate)}" />
                </label>
                <label>Timezone
                    <input type="text" name="zoneinfo" value="{$v($profile?->zoneinfo)}" placeholder="Europe/Madrid" />
                </label>
                <label>Locale
                    <input type="text" name="locale" value="{$v($profile?->locale)}" placeholder="es-ES" />
                </label>
                <label>Phone number
                    <input type="tel" name="phoneNumber" value="{$v($profile?->phoneNumber)}" />
                </label>
                <div class="profile-actions">
                    <input class="primary-button" type="submit" value="Save" />
                    <a class="secondary-button" href="{$cancelUrl}">Cancel</a>
                </div>
            </form>
            HTML;
    }
}
