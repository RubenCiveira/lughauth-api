<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\Oidc\Profile\Infrastructure\Driver\Html\Panels;

use Civi\Lughauth\Features\Oidc\Profile\Domain\OidcProfile;

class ProfileViewPanel
{
    public function render(?OidcProfile $profile, string $editUrl): string
    {
        $givenName = htmlspecialchars($profile?->givenName ?? '');
        $familyName = htmlspecialchars($profile?->familyName ?? '');
        $nickname = htmlspecialchars($profile?->nickname ?? '');
        $preferredUsername = htmlspecialchars($profile?->preferredUsername ?? '');
        $email = '';
        $pictureUrl = htmlspecialchars($profile?->pictureUrl ?? '');
        $websiteUrl = htmlspecialchars($profile?->websiteUrl ?? '');
        $phoneNumber = htmlspecialchars($profile?->phoneNumber ?? '');
        $locale = htmlspecialchars($profile?->locale ?? '');
        $zoneinfo = htmlspecialchars($profile?->zoneinfo ?? '');
        $birthdate = htmlspecialchars($profile?->birthdate ?? '');
        $gender = htmlspecialchars($profile?->gender ?? '');

        $avatar = $pictureUrl !== ''
            ? "<img src=\"{$pictureUrl}\" alt=\"Profile picture\" class=\"profile-avatar\" />"
            : '<div class="profile-avatar-placeholder"></div>';

        $name = trim("{$givenName} {$familyName}");
        $displayName = $name !== '' ? htmlspecialchars($name) : ($nickname !== '' ? $nickname : $preferredUsername);

        $editUrl = htmlspecialchars($editUrl);

        return <<<HTML
            <h1>My profile</h1>
            <div class="profile-view">
                <div class="profile-header">
                    {$avatar}
                    <div class="profile-display-name">{$displayName}</div>
                </div>
                <dl class="profile-fields">
                    <dt>Given name</dt><dd>{$givenName}</dd>
                    <dt>Family name</dt><dd>{$familyName}</dd>
                    <dt>Nickname</dt><dd>{$nickname}</dd>
                    <dt>Username</dt><dd>{$preferredUsername}</dd>
                    <dt>Phone</dt><dd>{$phoneNumber}</dd>
                    <dt>Locale</dt><dd>{$locale}</dd>
                    <dt>Timezone</dt><dd>{$zoneinfo}</dd>
                    <dt>Birthdate</dt><dd>{$birthdate}</dd>
                    <dt>Gender</dt><dd>{$gender}</dd>
                    <dt>Website</dt><dd>{$websiteUrl}</dd>
                </dl>
                <div class="profile-actions">
                    <a class="primary-button" href="{$editUrl}">Edit profile</a>
                </div>
            </div>
            HTML;
    }
}
