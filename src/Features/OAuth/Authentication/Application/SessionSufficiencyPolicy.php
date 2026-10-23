<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Application;

use DateTimeImmutable;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\Gateway\SessionSsoTtlGateway;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\IdTokenHint;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\OidcFlowContext;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\SessionDecision;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject\Prompt;
use Civi\Lughauth\Features\OAuth\Session\Domain\SessionInfo;
use Civi\Lughauth\Shared\Security\Acr;
use Civi\Lughauth\Shared\Security\AcrValues;

/**
 * Decides whether the browser session serves an authorization request (tasks 031 and 067).
 *
 * A session is reused only when the request does not force a new login (prompt=login or
 * select_account) and the user authenticated recently enough, counted from the real auth_time of
 * the session, never from the issuing time of a token: within the tenant's SSO lifetime and
 * within max_age when the client sent it. A session without auth_time is never fresh. A verified
 * id_token_hint naming another user makes the session unusable for the request: the hint never
 * selects or authenticates an account by itself. Otherwise the user signs in again, or the
 * request fails with login_required for prompt=none.
 *
 * A fresh session below the acr the client asked for (acr_values) is not reused either: the user
 * steps up with the second factor, or prompt=none fails with interaction_required.
 *
 * prompt=consent does not affect the session: the authorization endpoint shows the consent
 * screen again on top of it.
 */
class SessionSufficiencyPolicy
{
    public function __construct(
        private readonly SessionSsoTtlGateway $ssoTtl,
        private readonly AcrValues $acrValues,
    ) {
    }

    public function decide(OidcFlowContext $flow, ?SessionInfo $session, ?DateTimeImmutable $now = null, ?IdTokenHint $hint = null): SessionDecision
    {
        $now ??= new DateTimeImmutable();
        $prompt = $flow->prompts();
        $maxAge = $flow->maxAge();
        $fresh = $session !== null
            && !$prompt->forcesLogin()
            && ($hint === null || $hint->subject === $session->userId)
            && self::authenticatedWithin($session, $this->ssoTtl->forTenant($flow->tenant), $now)
            && ($maxAge === null || self::authenticatedWithin($session, $maxAge, $now));
        $required = $this->acrValues->preferred($flow->requestedAcrValues);
        $enough = $required === null || ($session !== null && Acr::fromAmr($session->amr)->satisfies($required));
        $silent = $prompt->has(Prompt::NONE);
        return match (true) {
            $fresh && $enough => SessionDecision::Reuse,
            $fresh => $silent ? SessionDecision::InteractionRequired : SessionDecision::StepUp,
            $silent => SessionDecision::LoginRequired,
            default => SessionDecision::Authenticate,
        };
    }

    private static function authenticatedWithin(SessionInfo $session, int $seconds, DateTimeImmutable $now): bool
    {
        return $session->authTime !== null && $now->getTimestamp() - $session->authTime->getTimestamp() <= $seconds;
    }
}
