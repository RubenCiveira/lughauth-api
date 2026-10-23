<?php

declare(strict_types=1);

namespace Civi\Lughauth\Shared\Exception;

/**
 * The access token is valid, but the user did not authenticate strongly or recently enough for
 * the operation (OAuth 2.0 Step-Up Authentication Challenge, RFC 9470).
 *
 * Answered with 401 and a `WWW-Authenticate: Bearer error="insufficient_user_authentication"`
 * challenge naming the acr_values and max_age to request (ErrorsPlugin). The client sends the user
 * to /authorize with them and repeats the call with the new token. Thrown by
 * AuthenticationContext::requireAcr; logic that decides by itself that the level is not enough
 * builds it with AuthenticationContext::stepUp, which names the configured acr value (AcrValues).
 */
class InsufficientUserAuthenticationException extends UnauthorizedException
{
    public function __construct(
        /** acr value to request with acr_values; null when only a more recent authentication is needed. */
        public readonly ?string $acrValues = null,
        /** Maximum seconds since the authentication, to request with max_age; null for no limit. */
        public readonly ?int $maxAge = null,
        string $message = 'A stronger or more recent authentication is required.',
    ) {
        parent::__construct($message);
    }

    /** Value of the WWW-Authenticate header (RFC 9470 §3). */
    public function challenge(): string
    {
        $params = [
            'error="insufficient_user_authentication"',
            'error_description="' . addcslashes($this->getMessage(), '"\\') . '"',
        ];
        if ($this->acrValues !== null) {
            $params[] = 'acr_values="' . addcslashes($this->acrValues, '"\\') . '"';
        }
        if ($this->maxAge !== null) {
            $params[] = 'max_age=' . $this->maxAge;
        }
        return 'Bearer ' . implode(', ', $params);
    }
}
