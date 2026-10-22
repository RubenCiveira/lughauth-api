<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject\UiLocale;

/**
 * The login catalogues of every supported UI language hold the same keys, every key the login
 * pages read exists, and no page title is a fixed text (task 068): a missing key or a literal
 * would show the raw key or the wrong language.
 */
final class LoginCatalogueUnitTest extends TestCase
{
    private const string TRANSLATIONS = __DIR__ . '/../../../../../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Translations';
    private const string HTML = __DIR__ . '/../../../../../../../src/Features/OAuth/Authentication/Infrastructure/Driver/Html';

    public function testEverySupportedLanguageHasTheSameKeys(): void
    {
        $base = $this->keys('en');
        foreach (UiLocale::SUPPORTED as $locale) {
            $this->assertSame($base, $this->keys($locale), "The {$locale} catalogue differs from the base one");
        }
    }

    public function testEveryKeyReadByTheLoginPagesExists(): void
    {
        $keys = $this->keys('en');
        foreach ($this->pages() as $page) {
            preg_match_all('/->get\(\s*["\']([a-z][a-z0-9-]*(?:\.[a-z0-9_-]+)+)["\']/', (string) file_get_contents($page), $used);
            // The result of this read is discarded, so a missing key is never shown.
            $read = array_diff($used[1], ['registeruser.code.newpass']);
            $this->assertSame([], array_values(array_diff($read, $keys)), basename($page) . ' reads keys missing from the catalogue');
        }
    }

    public function testNoLoginPageHasAFixedTitle(): void
    {
        foreach ($this->pages() as $page) {
            $source = (string) file_get_contents($page);
            $this->assertDoesNotMatchRegularExpression(
                '/getFullPage\(\s*\$[\w>-]+,\s*[\'"]/',
                $source,
                basename($page) . ' passes a fixed title to getFullPage instead of a translated one'
            );
        }
    }

    public function testNoLoginPageHasFixedVisibleText(): void
    {
        foreach ($this->pages() as $page) {
            preg_match_all('/<<<\s*"?HTML"?\n(.*?)\n\s*HTML/s', (string) file_get_contents($page), $documents);
            $fixed = [];
            foreach ($documents[1] as $html) {
                // Scripts and styles hold code, not text shown to the user.
                $html = (string) preg_replace('/<(script|style)\b.*?<\/\1>/s', '', $html);
                preg_match_all('/>([^<>]*)</s', $html, $nodes);
                foreach ($nodes[1] as $node) {
                    $visible = trim((string) preg_replace('/\{\$[^}]*\}|\$\w+/', '', $node));
                    if (preg_match('/\p{L}{2,}/u', $visible) === 1) {
                        $fixed[] = $visible;
                    }
                }
            }
            $this->assertSame([], $fixed, basename($page) . ' shows text that does not come from the catalogue');
        }
    }

    /** @return list<string> */
    private function pages(): array
    {
        return [...(glob(self::HTML . '/Forms/*.php') ?: []), self::HTML . '/AuthorizeHtml.php'];
    }

    /** @return list<string> */
    private function keys(string $locale): array
    {
        $file = self::TRANSLATIONS . ($locale === 'en' ? '/forms.yaml' : "/forms.{$locale}.yaml");
        $keys = $this->flatten((array) Yaml::parseFile($file));
        sort($keys);
        return $keys;
    }

    /** @return list<string> */
    private function flatten(array $tree, string $prefix = ''): array
    {
        $keys = [];
        foreach ($tree as $name => $value) {
            $key = $prefix === '' ? (string) $name : "{$prefix}.{$name}";
            $keys = [...$keys, ...(is_array($value) ? $this->flatten($value, $key) : [$key])];
        }
        return $keys;
    }
}
