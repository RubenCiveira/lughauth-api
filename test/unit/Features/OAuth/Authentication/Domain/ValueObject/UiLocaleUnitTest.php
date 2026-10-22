<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject\UiLocale;

/**
 * Unit tests for {@see UiLocale}: ui_locales first, Accept-Language as fallback (task 068).
 */
final class UiLocaleUnitTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'ui_locales wins over the header' => ['en', 'es-ES,es;q=0.9', 'en'],
            'first supported ui_locales in order' => ['fr en es', '', 'en'],
            'region subtag matches its language' => ['en-GB', '', 'en'],
            'underscore separator is accepted' => ['es_MX', '', 'es'],
            'tags are case insensitive' => ['EN-us', '', 'en'],
            'unsupported ui_locales fall back to the header' => ['fr de', 'en-US,en;q=0.9', 'en'],
            'header follows q-values, not order' => ['', 'fr;q=1, es;q=0.4, en;q=0.8', 'en'],
            'header keeps its order on equal q' => ['', 'es, en', 'es'],
            'q=0 means not acceptable' => ['', 'en;q=0, es;q=0.1', 'es'],
            'wildcard is not a language' => ['', '*, en;q=0.5', 'en'],
            'nothing supported uses the default' => ['fr', 'de-DE', UiLocale::DEFAULT],
            'nothing requested uses the default' => ['', '', UiLocale::DEFAULT],
        ];
    }

    #[DataProvider('cases')]
    public function testNegotiate(string $uiLocales, string $acceptLanguage, string $expected): void
    {
        $this->assertSame($expected, UiLocale::negotiate($uiLocales, $acceptLanguage));
    }
}
