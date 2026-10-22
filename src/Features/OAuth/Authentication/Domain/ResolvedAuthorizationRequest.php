<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain;

/**
 * Authorization request parameters after deciding where they come from (AuthorizationRequestResolver).
 *
 * A pushed (PAR) or signed (JAR) request is used as is and never merged with the query string.
 * Its parameters travel between the steps of the interactive login inside a signed reference,
 * because the request_uri and the request object can only be used once.
 */
final class ResolvedAuthorizationRequest
{
    /** Parameters taken from the query string: the client neither pushed nor signed them. */
    public const string ORIGIN_QUERY = 'query';
    /** Parameters pushed by the authenticated client to the PAR endpoint (RFC 9126). */
    public const string ORIGIN_PUSHED = 'par';
    /** Parameters signed by the client in a request object (RFC 9101). */
    public const string ORIGIN_SIGNED = 'jar';
    /** Query parameter carrying the signed reference between login steps. */
    public const string REFERENCE_PARAM = 'request_ref';

    public function __construct(
        /** Authorization parameters the flow must use (client_id, redirect_uri, scope, PKCE...). */
        public readonly array $params,
        /** One of the ORIGIN_* constants. */
        public readonly string $origin,
        /** Signed reference to these parameters for the next steps; null for a query request. */
        public readonly ?string $reference = null,
        /**
         * True when the PAR or JAR request was consumed by this HTTP request: the browser must be
         * sent to the reference before rendering any form, since forms post back to their URL.
         */
        public readonly bool $consumedNow = false,
    ) {
    }
}
