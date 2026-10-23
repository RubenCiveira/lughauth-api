<?php

declare(strict_types=1);

use Civi\Lughauth\Features\OAuth\Authentication\Application\SessionSufficiencyPolicy;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\Gateway\SessionSsoTtlGateway;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\IdTokenHint;
use Civi\Lughauth\Shared\Security\AcrValues;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\OidcFlowContext;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\SessionDecision;
use Civi\Lughauth\Features\OAuth\Session\Domain\SessionInfo;
use PHPUnit\Framework\TestCase;

final class SessionSufficiencyPolicyUnitTest extends TestCase
{
    private const array SSO_SECONDS = ['short' => 60, 'long' => 86400];

    public function testTheSsoLifetimeIsTheTenantOne(): void
    {
        $session = $this->session(new DateTimeImmutable('-600 seconds'));

        $this->assertSame(SessionDecision::Authenticate, $this->policy()->decide($this->flow('short'), $session));
        $this->assertSame(SessionDecision::Reuse, $this->policy()->decide($this->flow('long'), $session));
    }

    public function testASessionWithoutAuthTimeIsNeverFresh(): void
    {
        $this->assertSame(SessionDecision::Authenticate, $this->policy()->decide($this->flow('long'), $this->session(null)));
        $this->assertSame(SessionDecision::LoginRequired, $this->policy()->decide($this->flow('long', 'none'), $this->session(null)));
    }

    public function testMaxAgeCountsFromTheAuthentication(): void
    {
        $authTime = new DateTimeImmutable('2026-01-01 10:00:00');
        $flow = $this->flow('long', maxAge: '300');

        $this->assertSame(SessionDecision::Reuse, $this->policy()->decide($flow, $this->session($authTime), $authTime->modify('+300 seconds')));
        $this->assertSame(SessionDecision::Authenticate, $this->policy()->decide($flow, $this->session($authTime), $authTime->modify('+301 seconds')));
    }

    public function testASessionBelowTheRequestedAcrStepsUp(): void
    {
        $singleFactor = $this->session(new DateTimeImmutable('-60 seconds'), ['pwd']);

        $this->assertSame(SessionDecision::StepUp, $this->policy()->decide($this->flow('long', acrValues: 'urn:lugh:acr:mfa'), $singleFactor));
        $this->assertSame(SessionDecision::InteractionRequired, $this->policy()->decide($this->flow('long', 'none', acrValues: 'urn:lugh:acr:mfa'), $singleFactor));
        $this->assertSame(SessionDecision::Reuse, $this->policy()->decide($this->flow('long', acrValues: 'urn:lugh:acr:1fa'), $singleFactor));
    }

    public function testAMultiFactorSessionServesAStepUpRequest(): void
    {
        $session = $this->session(new DateTimeImmutable('-60 seconds'), ['pwd', 'otp', 'mfa']);

        $this->assertSame(SessionDecision::Reuse, $this->policy()->decide($this->flow('long', acrValues: 'urn:lugh:acr:mfa'), $session));
    }

    public function testUnknownAcrValuesAreIgnored(): void
    {
        $session = $this->session(new DateTimeImmutable('-60 seconds'), ['pwd']);

        $this->assertSame(SessionDecision::Reuse, $this->policy()->decide($this->flow('long', acrValues: 'urn:other:gold'), $session));
    }

    public function testAStaleSessionLogsInAgainRatherThanSteppingUp(): void
    {
        $stale = $this->session(new DateTimeImmutable('-600 seconds'), ['pwd']);

        $this->assertSame(SessionDecision::Authenticate, $this->policy()->decide($this->flow('short', acrValues: 'urn:lugh:acr:mfa'), $stale));
        $this->assertSame(SessionDecision::LoginRequired, $this->policy()->decide($this->flow('short', 'none', acrValues: 'urn:lugh:acr:mfa'), $stale));
    }

    public function testAnIdTokenHintOfAnotherUserKeepsTheSessionFromBeingReused(): void
    {
        $session = $this->session(new DateTimeImmutable('-60 seconds'));
        $flow = $this->flow('long');

        $this->assertSame(SessionDecision::Reuse, $this->policy()->decide($flow, $session, null, new IdTokenHint('user', 'old-sid', 'client')));
        $this->assertSame(SessionDecision::Authenticate, $this->policy()->decide($flow, $session, null, new IdTokenHint('other', '', 'client')));
        $this->assertSame(SessionDecision::LoginRequired, $this->policy()->decide($this->flow('long', 'none'), $session, null, new IdTokenHint('other', '', 'client')));
    }

    private function policy(): SessionSufficiencyPolicy
    {
        $ttl = $this->createStub(SessionSsoTtlGateway::class);
        $ttl->method('forTenant')->willReturnCallback(static fn (string $tenant): int => self::SSO_SECONDS[$tenant]);
        return new SessionSufficiencyPolicy($ttl, AcrValues::defaults());
    }

    private function flow(string $tenant, string $prompt = '', ?string $maxAge = null, ?string $acrValues = null): OidcFlowContext
    {
        return new OidcFlowContext($tenant, 'client', 'https://rp/cb', 'openid', 'code', '', '', [], $prompt, 'es', 'https://op', 'https://op/' . $tenant, null, null, null, null, requestedMaxAge: $maxAge, requestedAcrValues: $acrValues);
    }

    /** @param string[] $amr */
    private function session(?DateTimeImmutable $authTime, array $amr = ['pwd']): SessionInfo
    {
        return new SessionInfo('csid', false, 'https://op', 'user', 'client', authTime: $authTime, amr: $amr);
    }
}
