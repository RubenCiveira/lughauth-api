<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Session\Application;

use Civi\Lughauth\Features\OAuth\Session\Domain\Gateway\SessionStoreGateway;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\TokenRevocationGateway;
use Civi\Lughauth\Features\OAuth\WebAuthn\Domain\Gateway\WebAuthnChallengeGateway;

/**
 * Application service that orchestrates periodic cleanup of all expired OAuth resources.
 *
 * Runs three independent cleanup tasks in sequence — expired sessions, stale token revocations,
 * and expired WebAuthn challenges — collecting any errors per task rather than aborting the
 * entire run on a single failure. This approach ensures that a transient issue with one store
 * does not prevent the others from being purged. The result of each run is captured in a
 * CleanupResult value object that the caller (typically a cron job or CLI command) can inspect
 * for alerting or logging purposes. The scheduler is designed to be called from a cron job
 * or scheduler without any retry logic; if a task fails, the next scheduled run will retry it.
 */
final class OAuthCleanupScheduler
{
    public function __construct(
        private readonly SessionStoreGateway $sessions,
        private readonly TokenRevocationGateway $revocations,
        private readonly WebAuthnChallengeGateway $webAuthnChallenges,
    ) {
    }

    /**
     * Executes all cleanup tasks, capturing exceptions individually, and returns a summary result.
     * Each task failure is recorded by name so callers can identify which store had issues.
     */
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
            'sessions' => fn () => $this->sessions->purgeExpired(),
            'token_revocations' => fn () => $this->revocations->cleanup(),
            'webauthn_challenges' => fn () => $this->webAuthnChallenges->purgeExpired(),
        ];
    }
}
