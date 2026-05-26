<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Domain\Exception;

/**
 * Domain exception for GDPR-specific error conditions within the data access and erasure flows.
 *
 * Named factory methods map each recognisable failure mode to a machine-readable error code and
 * an appropriate HTTP status: unauthorized (403) for unauthenticated subject requests and
 * notFound (404) when no personal data is held for the given subject. The error() and
 * statusCode() accessors allow the REST layer to serialise the exception directly into the
 * standard JSON error response body without any additional conditional logic.
 */
final class GdprException extends \RuntimeException
{
    private function __construct(
        private readonly string $errorCode,
        private readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * Creates a 403 Forbidden exception for requests that lack a valid subject identity.
     * The caller must authenticate before accessing or erasing their personal data.
     */
    public static function unauthorized(): self
    {
        return new self('access_denied', 403, 'Subject authentication required to access GDPR data.');
    }

    /**
     * Creates a 404 Not Found exception when no personal data exists for the given subject in this tenant.
     * Used to signal that there is nothing to export or delete.
     */
    public static function notFound(): self
    {
        return new self('not_found', 404, 'No personal data found for the given subject.');
    }

    public function error(): string
    {
        return $this->errorCode;
    }

    public function statusCode(): int
    {
        return $this->status;
    }
}
