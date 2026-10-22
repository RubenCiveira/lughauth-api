<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Session\Application;

use Psr\SimpleCache\CacheInterface;
use Civi\Lughauth\Features\OAuth\Device\Domain\Gateway\DeviceAuthorizationGateway;
use Civi\Lughauth\Features\OAuth\Par\Domain\Gateway\ParRequestGateway;
use Civi\Lughauth\Features\OAuth\Session\Domain\Gateway\SessionStoreGateway;
use Civi\Lughauth\Features\OAuth\Session\Domain\Gateway\TemporalKeysGateway;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\ReplayJtiGateway;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\TokenRevocationGateway;
use Civi\Lughauth\Features\OAuth\WebAuthn\Domain\Gateway\WebAuthnChallengeGateway;
use Civi\Lughauth\Shared\Observability\LoggerAwareTrait;

/**
 * Application service that orchestrates periodic cleanup of all expired OAuth resources.
 *
 * Runs independent cleanup tasks in sequence — expired sessions, stale token revocations,
 * expired WebAuthn challenges, anti-replay JTIs, pushed authorization requests, device codes
 * and authorization codes — collecting any errors per task rather than aborting the entire run on a single
 * failure. This approach ensures that a transient issue with one store does not prevent the
 * others from being purged. The result of each run is captured in a CleanupResult value object.
 *
 * It does not depend on a cron job: OAuthPlugin calls runIfDue at the shutdown of every request
 * (after fastcgi_finish_request has flushed the response), and runIfDue lets one run through per
 * interval, so expired rows never pile up and the stores do not purge inside the request path.
 * If a task fails, the next run retries it.
 */
final class OAuthCleanupScheduler
{
    use LoggerAwareTrait;

    /** Minimum seconds between two runs: little enough to keep the backlog small, without a purge per request. */
    private const int INTERVAL_SECONDS = 60;
    private const string LAST_RUN_KEY = 'oauth.cleanup.last-run';

    public function __construct(
        private readonly SessionStoreGateway $sessions,
        private readonly TokenRevocationGateway $revocations,
        private readonly WebAuthnChallengeGateway $webAuthnChallenges,
        private readonly ReplayJtiGateway $replayJtis,
        private readonly ParRequestGateway $parRequests,
        private readonly DeviceAuthorizationGateway $deviceAuthorizations,
        private readonly TemporalKeysGateway $authorizationCodes,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Runs the cleanup unless another request already ran it within the interval; returns null
     * when skipped. Two requests racing past the check both purge, which is harmless.
     */
    public function runIfDue(): ?CleanupResult
    {
        if ($this->cache->has(self::LAST_RUN_KEY)) {
            return null;
        }
        $this->cache->set(self::LAST_RUN_KEY, time(), self::INTERVAL_SECONDS);
        $result = $this->run();
        if (!$result->succeeded()) {
            $this->logWarning('OAuth cleanup tasks failed', $result->errors);
        }
        return $result;
    }

    /** SQLSTATE of a missing table (MySQL, PostgreSQL): nothing to purge until migrations run. */
    private const array MISSING_TABLE_STATES = ['42S02', '42P01'];

    /**
     * Executes all cleanup tasks, capturing exceptions individually, and returns a summary result.
     * Each task failure is recorded by name so callers can identify which store had issues; a
     * store whose table does not exist yet is skipped, since it holds nothing to purge.
     */
    public function run(): CleanupResult
    {
        $errors = [];

        foreach ($this->tasks() as $name => $task) {
            try {
                $task();
            } catch (\PDOException $ex) {
                if (!in_array($ex->getCode(), self::MISSING_TABLE_STATES, true)) {
                    $errors[$name] = $ex->getMessage();
                }
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
            'replay_jtis' => fn () => $this->replayJtis->purgeExpired(),
            'par_requests' => fn () => $this->parRequests->purgeExpired(),
            'device_codes' => fn () => $this->deviceAuthorizations->purgeExpired(),
            'authorization_codes' => fn () => $this->authorizationCodes->purgeExpiredCodes(),
        ];
    }
}
