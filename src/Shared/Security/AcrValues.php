<?php

declare(strict_types=1);

namespace Civi\Lughauth\Shared\Security;

use InvalidArgumentException;
use Civi\Lughauth\Shared\AppConfig;

/**
 * The acr values that name each assurance level (Acr), taken from configuration so they can be
 * agreed with other identity providers and relying parties (task 067).
 *
 * Each level reads a comma or space separated list (`security.acr.single-factor` and
 * `security.acr.multi-factor`, or the SECURITY_ACR_SINGLE_FACTOR and SECURITY_ACR_MULTI_FACTOR
 * environment variables). The first value is the one this server issues in tokens, publishes in
 * discovery and asks for in step-up challenges; the others are aliases it also accepts, in
 * acr_values and in tokens of other issuers. A value cannot name two levels.
 */
final class AcrValues
{
    /** @var array<string, Acr> every accepted value, issued or alias, with its level */
    private readonly array $levels;

    /** @var array<string, string> issued value by level name */
    private readonly array $issued;

    /** @param array<string, list<string>> $values accepted values by level name, the issued one first */
    private function __construct(array $values)
    {
        $levels = [];
        $issued = [];
        foreach (Acr::cases() as $acr) {
            $list = $values[$acr->name] ?? [];
            $issued[$acr->name] = $list[0] ?? $acr->defaultValue();
            foreach ($list === [] ? [$acr->defaultValue()] : $list as $value) {
                if (isset($levels[$value]) && $levels[$value] !== $acr) {
                    throw new InvalidArgumentException("The acr value {$value} names two levels.");
                }
                $levels[$value] = $acr;
            }
        }
        $this->levels = $levels;
        $this->issued = $issued;
    }

    public static function fromConfig(AppConfig $config): self
    {
        $values = [];
        foreach (Acr::cases() as $acr) {
            $values[$acr->name] = self::parse((string) $config->get($acr->configKey(), ''));
        }
        return new self($values);
    }

    /** The default values (urn:lugh:acr:1fa and urn:lugh:acr:mfa), without aliases. */
    public static function defaults(): self
    {
        return new self([]);
    }

    /** acr value this server issues and publishes for $acr. */
    public function valueOf(Acr $acr): string
    {
        return $this->issued[$acr->name];
    }

    /** Level named by an acr value, issued or alias, or null for a value it does not know. */
    public function levelOf(?string $value): ?Acr
    {
        return $this->levels[(string) $value] ?? null;
    }

    /**
     * The most preferred value of a space-separated acr_values that names a known level, or null
     * when it names none: unknown values are ignored, as acr_values is a voluntary request.
     */
    public function preferred(?string $acrValues): ?Acr
    {
        foreach (self::parse((string) $acrValues) as $value) {
            $acr = $this->levelOf($value);
            if ($acr !== null) {
                return $acr;
            }
        }
        return null;
    }

    /**
     * Values published as acr_values_supported: the issued one of each level.
     *
     * @return list<string>
     */
    public function supported(): array
    {
        return array_values($this->issued);
    }

    /** @return list<string> */
    private static function parse(string $list): array
    {
        return array_values(array_filter(preg_split('/[\s,]+/', trim($list)) ?: [], static fn (string $value): bool => $value !== ''));
    }
}
