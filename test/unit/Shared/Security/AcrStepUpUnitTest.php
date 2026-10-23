<?php

declare(strict_types=1);

use Civi\Lughauth\Shared\AppConfig;
use Civi\Lughauth\Shared\Exception\InsufficientUserAuthenticationException;
use Civi\Lughauth\Shared\Exception\UnauthorizedException;
use Civi\Lughauth\Shared\Security\Acr;
use Civi\Lughauth\Shared\Security\AcrValues;
use Civi\Lughauth\Shared\Security\AuthenticationContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The assurance levels, the acr values configured for them, and the check a resource server
 * makes before a sensitive operation: only the acr and auth_time the token carries count, and a
 * shortfall becomes a RFC 9470 challenge naming the configured value.
 */
final class AcrStepUpUnitTest extends TestCase
{
    private const string CORPORATE = 'security.acr.multi-factor';

    public static function sessions(): array
    {
        return [
            'password' => [['pwd'], Acr::SINGLE_FACTOR],
            'passkey' => [['pop', 'user'], Acr::SINGLE_FACTOR],
            'federated, no recorded method' => [[], Acr::SINGLE_FACTOR],
            'password and totp' => [['pwd', 'otp', 'mfa'], Acr::MULTI_FACTOR],
            'password and recovery code' => [['pwd', 'mfa'], Acr::MULTI_FACTOR],
        ];
    }

    #[DataProvider('sessions')]
    public function testTheLevelComesFromTheMethodsRecorded(array $amr, Acr $acr): void
    {
        $this->assertSame($acr, Acr::fromAmr($amr));
    }

    public function testMultiFactorSatisfiesEveryLevelAndSingleFactorOnlyItself(): void
    {
        $this->assertSame(
            [true, true, true, false],
            [
                Acr::MULTI_FACTOR->satisfies(Acr::MULTI_FACTOR),
                Acr::MULTI_FACTOR->satisfies(Acr::SINGLE_FACTOR),
                Acr::SINGLE_FACTOR->satisfies(Acr::SINGLE_FACTOR),
                Acr::SINGLE_FACTOR->satisfies(Acr::MULTI_FACTOR),
            ]
        );
    }

    public function testWithoutConfigurationTheLevelsHaveTheirDefaultValues(): void
    {
        $values = AcrValues::fromConfig($this->config([]));

        $this->assertSame(['urn:lugh:acr:1fa', 'urn:lugh:acr:mfa'], $values->supported());
        $this->assertSame(Acr::MULTI_FACTOR, $values->levelOf('urn:lugh:acr:mfa'));
        $this->assertNull($values->levelOf('urn:other:gold'));
    }

    /**
     * The first configured value is the one issued and published; the others are aliases other
     * identity providers and relying parties use, accepted in tokens and in acr_values.
     */
    public function testTheConfiguredValuesNameTheLevels(): void
    {
        $values = AcrValues::fromConfig($this->config([
            self::CORPORATE => 'urn:corp:loa:3, http://schemas.openid.net/pape/policies/2007/06/multi-factor',
        ]));

        $this->assertSame('urn:corp:loa:3', $values->valueOf(Acr::MULTI_FACTOR));
        $this->assertSame('urn:lugh:acr:1fa', $values->valueOf(Acr::SINGLE_FACTOR));
        $this->assertSame(['urn:lugh:acr:1fa', 'urn:corp:loa:3'], $values->supported());
        $this->assertSame(Acr::MULTI_FACTOR, $values->levelOf('http://schemas.openid.net/pape/policies/2007/06/multi-factor'));
        $this->assertNull($values->levelOf('urn:lugh:acr:mfa'), 'the default is replaced, not kept as an alias');
    }

    public function testTheFirstKnownRequestedValueIsThePreferredOne(): void
    {
        $values = AcrValues::defaults();

        $this->assertSame(
            [Acr::MULTI_FACTOR, Acr::SINGLE_FACTOR, null, null],
            [
                $values->preferred('urn:other:gold  urn:lugh:acr:mfa urn:lugh:acr:1fa'),
                $values->preferred('urn:lugh:acr:1fa urn:lugh:acr:mfa'),
                $values->preferred('urn:other:gold'),
                $values->preferred(null),
            ]
        );
    }

    public function testAValueCannotNameTwoLevels(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AcrValues::fromConfig($this->config([
            'security.acr.single-factor' => 'urn:corp:loa',
            self::CORPORATE => 'urn:corp:loa',
        ]));
    }

    public function testATokenSatisfiesTheLevelWithinMaxAgeOfItsAuthTime(): void
    {
        $now = new DateTimeImmutable('2026-10-23 10:00:00');
        $context = $this->context('urn:lugh:acr:mfa', $now->modify('-300 seconds'));

        $this->assertTrue($context->satisfies(Acr::MULTI_FACTOR, null, $now));
        $this->assertTrue($context->satisfies(Acr::MULTI_FACTOR, 300, $now));
        $this->assertFalse($context->satisfies(Acr::MULTI_FACTOR, 299, $now));
    }

    public function testWithoutAuthTimeMaxAgeIsNeverMet(): void
    {
        $context = $this->context('urn:lugh:acr:mfa', null);

        $this->assertTrue($context->satisfies(Acr::MULTI_FACTOR));
        $this->assertFalse($context->satisfies(Acr::MULTI_FACTOR, 3600), 'the issue time is no authentication');
    }

    public function testAnUnknownOrMissingAcrSatisfiesNothing(): void
    {
        $this->assertFalse($this->context('urn:other:gold', new DateTimeImmutable())->satisfies(Acr::SINGLE_FACTOR));
        $this->assertFalse(AuthenticationContext::anonymous()->satisfies(Acr::SINGLE_FACTOR));
    }

    public function testATokenOfAnotherIssuerIsReadWithTheConfiguredAliases(): void
    {
        $values = AcrValues::fromConfig($this->config([self::CORPORATE => 'urn:corp:loa:3 urn:partner:mfa']));
        $context = $this->context('urn:partner:mfa', new DateTimeImmutable(), $values);

        $this->assertSame(Acr::MULTI_FACTOR, $context->getAcrLevel());
        $this->assertTrue($context->satisfies(Acr::MULTI_FACTOR));
    }

    public function testAShortfallIsAStepUpChallengeForTheConfiguredValue(): void
    {
        $values = AcrValues::fromConfig($this->config([self::CORPORATE => 'urn:corp:loa:3']));
        $context = $this->context('urn:lugh:acr:1fa', new DateTimeImmutable(), $values);

        try {
            $context->requireAcr(Acr::MULTI_FACTOR, 300);
            $this->fail('A single factor token cannot reach a multi factor operation');
        } catch (InsufficientUserAuthenticationException $ex) {
            $this->assertInstanceOf(UnauthorizedException::class, $ex);
            $this->assertSame(401, $ex->getCode());
            $this->assertSame(
                'Bearer error="insufficient_user_authentication", '
                . 'error_description="A stronger or more recent authentication is required.", '
                . 'acr_values="urn:corp:loa:3", max_age=300',
                $ex->challenge()
            );
        }
    }

    public function testTheLogicCanBuildItsOwnChallenge(): void
    {
        $context = $this->context('urn:lugh:acr:mfa', new DateTimeImmutable());

        $this->assertSame(
            'Bearer error="insufficient_user_authentication", error_description="Confirm the \"transfer\"", max_age=0',
            $context->stepUp(null, 0, 'Confirm the "transfer"')->challenge()
        );
        $this->assertSame('urn:lugh:acr:mfa', $context->stepUp(Acr::MULTI_FACTOR)->acrValues);
    }

    private function context(?string $acr, ?DateTimeImmutable $authTime, ?AcrValues $values = null): AuthenticationContext
    {
        return new AuthenticationContext(level: AuthenticationContext::LEVEL_PASSWORD, acr: $acr, authTime: $authTime, acrValues: $values);
    }

    /** @param array<string, string> $values */
    private function config(array $values): AppConfig
    {
        $config = $this->createStub(AppConfig::class);
        $config->method('get')->willReturnCallback(static fn (string $name, mixed $default = null): mixed => $values[$name] ?? $default);
        return $config;
    }
}
