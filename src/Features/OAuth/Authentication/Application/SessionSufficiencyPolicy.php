<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Application;

use DateTimeImmutable;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\Gateway\SessionSsoTtlGateway;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\OidcFlowContext;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\SessionDecision;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject\Prompt;
use Civi\Lughauth\Features\OAuth\Session\Domain\SessionInfo;

/**
 * Decides whether the browser session serves an authorization request (task 031).
 *
 * A session is reused only when the request does not force a new login (prompt=login or
 * select_account) and the user authenticated recently enough, counted from the real auth_time of
 * the session, never from the issuing time of a token: within the tenant's SSO lifetime and
 * within max_age when the client sent it. A session without auth_time is never fresh. Otherwise
 * the user signs in again, or the request fails with login_required for prompt=none.
 *
 * prompt=consent does not affect the session: the authorization endpoint shows the consent
 * screen again on top of it.
 */
class SessionSufficiencyPolicy
{
    public function __construct(
        private readonly SessionSsoTtlGateway $ssoTtl,
    ) {
    }

    public function decide(OidcFlowContext $flow, ?SessionInfo $session, ?DateTimeImmutable $now = null): SessionDecision
    {
        $now ??= new DateTimeImmutable();
        $prompt = $flow->prompts();
        $maxAge = $flow->maxAge();
        $reusable = $session !== null
            && !$prompt->forcesLogin()
            && self::authenticatedWithin($session, $this->ssoTtl->forTenant($flow->tenant), $now)
            && ($maxAge === null || self::authenticatedWithin($session, $maxAge, $now));
        return match (true) {
            $reusable => SessionDecision::Reuse,
            $prompt->has(Prompt::NONE) => SessionDecision::LoginRequired,
            default => SessionDecision::Authenticate,
        };
    }

    private static function authenticatedWithin(SessionInfo $session, int $seconds, DateTimeImmutable $now): bool
    {
        return $session->authTime !== null && $now->getTimestamp() - $session->authTime->getTimestamp() <= $seconds;
    }
}
