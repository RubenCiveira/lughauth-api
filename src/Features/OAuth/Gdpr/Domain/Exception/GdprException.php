<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Gdpr\Domain\Exception;

final class GdprException extends \RuntimeException
{
    private function __construct(
        private readonly string $errorCode,
        private readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function unauthorized(): self
    {
        return new self('access_denied', 403, 'Subject authentication required to access GDPR data.');
    }

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
