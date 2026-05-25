<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Session\Application;

use Civi\Lughauth\Features\OAuth\Session\Domain\Gateway\SessionStoreGateway;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\TokenRevocationGateway;
use Civi\Lughauth\Features\OAuth\WebAuthn\Domain\Gateway\WebAuthnChallengeGateway;

/**
 * Orchestrates periodic cleanup of all expired OAuth resources.
 * Designed to be called from a cron job or scheduler.
 */
final class OAuthCleanupScheduler
{
    public function __construct(
        private readonly SessionStoreGateway $sessions,
        private readonly TokenRevocationGateway $revocations,
        private readonly WebAuthnChallengeGateway $webAuthnChallenges,
    ) {
    }

    public function run(): CleanupResult
    {
        $errors = [];

        foreach ($this->tasks() as $name => $task) {
            try {
                $task();
            } catch (\Throwable $ex) {
                $errors[$name] = $ex->getMessage();
            }
        }

        return new CleanupResult(
            executedAt: new \DateTimeImmutable(),
            errors: $errors,
        );
    }

    /** @return array<string, callable> */
    private function tasks(): array
    {
        return [
            'sessions' => fn() => $this->sessions->purgeExpired(),
            'token_revocations' => fn() => $this->revocations->cleanup(),
            'webauthn_challenges' => fn() => $this->webAuthnChallenges->purgeExpired(),
        ];
    }
}
