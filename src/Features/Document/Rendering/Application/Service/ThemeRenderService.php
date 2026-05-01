<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\Document\Rendering\Application\Service;

use Civi\Lughauth\Shared\AppConfig;
use Civi\Lughauth\Shared\Context;
use Civi\Lughauth\Features\Access\Tenant\Domain\TenantRef;
use Civi\Lughauth\Features\Document\Rendering\Domain\TemplateOutputFormat;
use Civi\Lughauth\Features\Document\Rendering\Domain\TemplateRenderRequest;
use Civi\Lughauth\Features\Document\Rendering\Domain\Gateway\TemplateRenderGateway;
use Civi\Lughauth\Features\Document\Theme\Domain\Theme;
use Civi\Lughauth\Features\Document\Theme\Domain\Gateway\ThemeReadGateway;
use Civi\Lughauth\Features\Document\ThemeVersion\Domain\ThemeVersion;
use Civi\Lughauth\Features\Document\ThemeVersion\Domain\ThemeVersionChannelOptions;
use Civi\Lughauth\Features\Document\ThemeVersion\Domain\Gateway\ThemeVersionFilter;
use Civi\Lughauth\Features\Document\ThemeVersion\Domain\Gateway\ThemeVersionReadGateway;
use Civi\Lughauth\Features\Document\ThemeAsset\Domain\Gateway\ThemeAssetFilter;
use Civi\Lughauth\Features\Document\ThemeAsset\Domain\Gateway\ThemeAssetReadGateway;

class ThemeRenderService
{
    private readonly string $assetsBase;

    public function __construct(
        AppConfig $config,
        Context $context,
        private readonly ThemeReadGateway $themeGateway,
        private readonly ThemeVersionReadGateway $themeVersionGateway,
        private readonly ThemeAssetReadGateway $themeAssetGateway,
        private readonly TemplateRenderGateway $renderGateway,
    ) {
        $this->assetsBase = $config->get('oidc.theme.path', $context->getBaseUrl() . '/');
    }

    /**
     * Wrap $innerContent in the ThemeVersion HTML for the given theme name and channel.
     *
     * Resolves the theme tenant-specifically first, then falls back to the global theme.
     * Picks the best ThemeVersion using BCP 47 locale negotiation.
     * Returns null when no enabled theme or version is found (caller must handle the fallback).
     *
     * @param array<string, string> $extraVars Additional variables merged into the theme template (title, subject, …).
     */
    public function render(
        string $themeName,
        ThemeVersionChannelOptions $channel,
        string $innerContent,
        ?string $locale,
        ?TenantRef $tenantRef,
        array $extraVars = [],
    ): ?string {
        $theme = $this->resolveTheme($themeName, $tenantRef);
        if ($theme === null) {
            return null;
        }

        $version = $this->bestVersion($theme, $channel, $locale);
        if ($version === null) {
            return null;
        }

        $html = $version->getContentHtml();
        if ($html === null || $html === '') {
            return null;
        }

        $assetsPath = $this->dumpAssets($theme);

        $vars = array_merge($extraVars, [
            'slot_content'      => $innerContent,
            'locale'            => $locale ?? '',
            'theme_assets_path' => $assetsPath,
        ]);

        $rendered = $this->renderGateway->render(new TemplateRenderRequest(
            htmlContent: $html,
            snippets: [],
            variables: $vars,
            outputFormat: TemplateOutputFormat::HTML,
            subject: null,
            textContent: null,
            assets: [],
        ));

        return $rendered->htmlContent();
    }

    private function resolveTheme(string $name, ?TenantRef $tenantRef): ?Theme
    {
        if ($tenantRef !== null) {
            $theme = $this->themeGateway->findOneByNameAndTenant($name, $tenantRef);
            if ($theme !== null && $theme->isEnabled()) {
                return $theme;
            }
        }

        $theme = $this->themeGateway->findOneByNameAndTenant($name, null);

        return ($theme !== null && $theme->isEnabled()) ? $theme : null;
    }

    private function bestVersion(Theme $theme, ThemeVersionChannelOptions $channel, ?string $locale): ?ThemeVersion
    {
        $all = $this->themeVersionGateway
            ->list((new ThemeVersionFilter())->withTheme($theme))
            ->values();

        $byChannel = array_values(array_filter(
            $all,
            static fn (ThemeVersion $v) => $v->getChannel() === $channel
        ));

        if (empty($byChannel)) {
            return null;
        }

        // BCP 47: exact locale match
        if ($locale !== null) {
            foreach ($byChannel as $v) {
                if ($v->getLocale() === $locale) {
                    return $v;
                }
            }
            // language-prefix match (e.g. "es" matches "es-ES")
            $lang = substr($locale, 0, 2);
            foreach ($byChannel as $v) {
                if ($v->getLocale() !== null && str_starts_with($v->getLocale(), $lang)) {
                    return $v;
                }
            }
        }

        // default (locale-less) version
        foreach ($byChannel as $v) {
            if ($v->getLocale() === null) {
                return $v;
            }
        }

        return $byChannel[0];
    }

    private function dumpAssets(Theme $theme): string
    {
        $themeUid  = $theme->uid() ?? '';
        $dirName   = "themes/{$themeUid}";
        $realPath  = realpath('.');
        $assetsDir = ($realPath !== false ? $realPath : '.') . "/.assets/{$dirName}";

        if (!is_dir($assetsDir)) {
            mkdir($assetsDir, 0777, true);
        }

        $slide = $this->themeAssetGateway->list(
            (new ThemeAssetFilter())->withTheme($theme)
        );

        foreach ($slide->values() as $asset) {
            if (!$asset->isEnabled()) {
                continue;
            }
            $code = $asset->getCode();
            if ($code === '' || str_contains($code, '/') || str_contains($code, '..')) {
                continue;
            }
            $binary = $this->themeAssetGateway->readContent($asset->getContent());
            if (!$binary->isStreamOpen()) {
                continue;
            }
            $fp = fopen($assetsDir . '/' . $code, 'wb');
            if ($fp !== false) {
                stream_copy_to_stream($binary->stream, $fp);
                fclose($fp);
            }
        }

        return "{$this->assetsBase}.assets/{$dirName}";
    }
}
