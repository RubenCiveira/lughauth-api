<?php

declare(strict_types=1);

use Civi\Lughauth\Features\OAuth\Authentication\Application\SessionSufficiencyPolicy;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\Gateway\SessionSsoTtlGateway;
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

    private function policy(): SessionSufficiencyPolicy
    {
        $ttl = $this->createStub(SessionSsoTtlGateway::class);
        $ttl->method('forTenant')->willReturnCallback(static fn (string $tenant): int => self::SSO_SECONDS[$tenant]);
        return new SessionSufficiencyPolicy($ttl);
    }

    private function flow(string $tenant, string $prompt = '', ?string $maxAge = null): OidcFlowContext
    {
        return new OidcFlowContext($tenant, 'client', 'https://rp/cb', 'openid', 'code', '', '', [], $prompt, 'es', 'https://op', 'https://op/' . $tenant, null, null, null, null, requestedMaxAge: $maxAge);
    }

    private function session(?DateTimeImmutable $authTime): SessionInfo
    {
        return new SessionInfo('csid', false, 'https://op', 'user', 'client', authTime: $authTime);
    }
}
