<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject;

/**
 * Language of the login UI for one authorization request (task 068).
 *
 * The client chooses it with `ui_locales` (OIDC Core §3.1.2.1: space-separated BCP 47 tags in
 * order of preference); the browser's Accept-Language is the fallback, in q-value order. The
 * first supported language wins, matched exactly or by its primary subtag (`es-ES` → `es`).
 * It only applies to this request: it is never stored in the user's profile.
 */
final class UiLocale
{
    /** Languages with a login catalogue (Translations/forms.yaml is `en`, forms.es.yaml is `es`). */
    public const array SUPPORTED = ['es', 'en'];
    /** Used when neither ui_locales nor Accept-Language name a supported language. */
    public const string DEFAULT = 'es';

    /**
     * Returns the supported language for the requested ui_locales and Accept-Language header.
     */
    public static function negotiate(string $uiLocales, string $acceptLanguage): string
    {
        $requested = preg_split('/\s+/', trim($uiLocales), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ([...$requested, ...self::byQuality($acceptLanguage)] as $tag) {
            $tag = strtolower(str_replace('_', '-', $tag));
            foreach ([$tag, explode('-', $tag)[0]] as $candidate) {
                if (in_array($candidate, self::SUPPORTED, true)) {
                    return $candidate;
                }
            }
        }
        return self::DEFAULT;
    }

    /**
     * Accept-Language tags by descending q-value (RFC 9110 §12.5.4), keeping the header order on
     * ties and dropping the ones with q=0.
     *
     * @return list<string>
     */
    private static function byQuality(string $header): array
    {
        $weighted = [];
        foreach (explode(',', $header) as $position => $item) {
            $parts = explode(';', $item);
            $tag = trim($parts[0]);
            $quality = 1.0;
            foreach (array_slice($parts, 1) as $parameter) {
                if (preg_match('/^\s*q\s*=\s*([0-9.]+)\s*$/i', $parameter, $match) === 1) {
                    $quality = (float) $match[1];
                }
            }
            if ($tag !== '' && $tag !== '*' && $quality > 0) {
                $weighted[] = [$tag, $quality, $position];
            }
        }
        usort($weighted, static fn (array $a, array $b): int => [$b[1], $a[2]] <=> [$a[1], $b[2]]);
        return array_column($weighted, 0);
    }
}
