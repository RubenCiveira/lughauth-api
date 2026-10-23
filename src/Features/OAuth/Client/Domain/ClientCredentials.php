<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Client\Domain;

/**
 * Client credentials presented in a request to the token, PAR, introspection or revocation
 * endpoint, before they are verified by ClientAuthenticator.
 *
 * They come from HTTP Basic (client_secret_basic), from the client_secret body parameter
 * (client_secret_post), from a JWT client assertion (private_key_jwt, RFC 7523 §2.2) or from the
 * client_id alone (none). A client uses exactly one method (RFC 6749 §2.3): method() is null when
 * the request mixes them, has a malformed Basic header or names two different clients.
 */
final class ClientCredentials
{
    /** client_assertion_type of a JWT client assertion (RFC 7523 §2.2). */
    public const string ASSERTION_TYPE = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';
    /** Body parameters that authenticate the client and are never authorization parameters. */
    public const array BODY_PARAMS = ['client_secret', 'client_assertion', 'client_assertion_type'];

    /**
     * @param array{string, string}|null $basic client id and secret of the Basic header
     */
    public function __construct(
        /** The client_id body parameter. */
        public readonly ?string $clientId = null,
        public readonly ?array $basic = null,
        /** The client_secret body parameter. */
        public readonly ?string $secret = null,
        public readonly ?string $assertionType = null,
        public readonly ?string $assertion = null,
        /** The Authorization header uses the Basic scheme but cannot be decoded. */
        public readonly bool $malformed = false,
    ) {
    }

    /**
     * Reads the credentials from the Authorization header (or the PHP_AUTH_* server parameters
     * the web server may leave instead) and the form body. Other Authorization schemes are not
     * client credentials and are ignored.
     */
    public static function fromHttp(string $authorization, array $server, array $body): self
    {
        $basic = null;
        $malformed = false;
        if (preg_match('/^Basic\s+(.*)$/i', $authorization, $matches)) {
            $decoded = base64_decode(trim($matches[1]), true);
            if ($decoded === false || !str_contains($decoded, ':')) {
                $malformed = true;
            } else {
                [$id, $secret] = explode(':', $decoded, 2);
                // RFC 6749 §2.3.1: both are form-urlencoded before being joined.
                $basic = [urldecode($id), urldecode($secret)];
            }
        } elseif (isset($server['PHP_AUTH_USER'], $server['PHP_AUTH_PW'])) {
            $basic = [(string) $server['PHP_AUTH_USER'], (string) $server['PHP_AUTH_PW']];
        }
        $string = static fn (string $name): ?string => is_string($body[$name] ?? null) && $body[$name] !== '' ? $body[$name] : null;
        return new self(
            clientId: $string('client_id'),
            basic: $basic,
            secret: $string('client_secret'),
            assertionType: $string('client_assertion_type'),
            assertion: $string('client_assertion'),
            malformed: $malformed,
        );
    }

    /**
     * The authentication method the credentials use (client_secret_basic, client_secret_post,
     * private_key_jwt or none), or null when they are malformed or mix several methods.
     */
    public function method(): ?string
    {
        $methods = array_keys(array_filter([
            'client_secret_basic' => $this->basic !== null,
            'client_secret_post' => $this->secret !== null,
            'private_key_jwt' => $this->assertionType !== null || $this->assertion !== null,
        ]));
        if ($this->malformed || count($methods) > 1 || ($this->clientId() === null && $methods !== ['private_key_jwt'])) {
            return null;
        }
        return $methods[0] ?? 'none';
    }

    /**
     * The client the credentials name: the Basic user or the client_id parameter. Null when there
     * is none or they differ. A client assertion may omit it, as the assertion names the client.
     */
    public function clientId(): ?string
    {
        if ($this->basic !== null && $this->clientId !== null && $this->basic[0] !== $this->clientId) {
            return null;
        }
        return $this->basic[0] ?? $this->clientId;
    }

    /** The secret of client_secret_basic or client_secret_post. */
    public function secret(): string
    {
        return $this->basic[1] ?? $this->secret ?? '';
    }
}
