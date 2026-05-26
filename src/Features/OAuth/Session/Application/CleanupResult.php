<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Session\Application;

/**
 * Value object returned by OAuthCleanupScheduler after a cleanup run completes.
 *
 * Records when the run was executed and collects any per-task errors so the caller can
 * log or alert without the scheduler itself throwing. A run is considered successful only
 * when every cleanup task completed without error, which is indicated by the succeeded()
 * helper method. Each key in the errors array is the task name, and the value is the
 * exception message captured during that task's execution.
 */
final class CleanupResult
{
    /**
     * The timestamp at which the cleanup run was completed.
     * Useful for logging and scheduling systems to track the last successful execution time.
     */
    public readonly \DateTimeImmutable $executedAt;

    /**
     * Map of task name to error message for every task that failed during the run.
     * An empty array means all tasks completed successfully.
     *
     * @param array<string, string> $errors
     */
    public readonly array $errors;

    /** @param array<string, string> $errors */
    public function __construct(
        \DateTimeImmutable $executedAt,
        array $errors,
    ) {
        $this->executedAt = $executedAt;
        $this->errors = $errors;
    }

    /**
     * Returns true when the cleanup run completed without any task errors.
     * A false return means at least one cleanup task threw an exception; check the errors array for details.
     */
    public function succeeded(): bool
    {
        return $this->errors === [];
    }
}
