<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Client\Application;

use Civi\Lughauth\Features\OAuth\Client\Domain\ClientAuthPolicy;
use Civi\Lughauth\Features\OAuth\Client\Domain\ClientCredentials;
use Civi\Lughauth\Features\OAuth\Client\Domain\ClientData;
use Civi\Lughauth\Features\OAuth\Client\Domain\Gateway\ClientStoreGateway;
use Civi\Lughauth\Features\OAuth\Client\Domain\JwtAssertionVerifier;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\ReplayPurpose;
use Civi\Lughauth\Shared\Observability\LoggerAwareTrait;

/**
 * Single point of client authentication for the token, PAR, introspection and revocation
 * endpoints (task 044).
 *
 * The credentials must use exactly one method. Secrets are verified by ClientStoreGateway
 * (client_secret_basic and client_secret_post); the client_id alone authenticates a client allowed
 * public access (none); and a JWT client assertion (private_key_jwt, RFC 7523 §3) is verified by
 * JwtAssertionVerifier: signed with the client's keys and token_endpoint_auth_signing_alg, iss and
 * sub equal to the client, aud naming the tenant issuer, its token endpoint or the endpoint called,
 * and a jti used once, also never as a request object. An assertion carrying response_type is an
 * authorization request object (RequestObjectValidator requires it) and is rejected.
 *
 * The policy decides which secret-based methods an endpoint accepts. private_key_jwt is accepted
 * everywhere, but only from a client registered for it, and such a client never authenticates
 * with a secret or as a public client.
 */
final class ClientAuthenticator
{
    use LoggerAwareTrait;

    private const string PRIVATE_KEY_JWT = 'private_key_jwt';

    public function __construct(
        private readonly ClientStoreGateway $clients,
        private readonly JwtAssertionVerifier $assertions,
    ) {
    }

    /**
     * Returns the authenticated client of the tenant, or null when the credentials do not
     * authenticate it (invalid_client).
     *
     * @param string $issuer the tenant issuer
     * @param string $endpoint URL of the endpoint called, also accepted as assertion audience
     */
    public function authenticate(string $tenant, ClientCredentials $credentials, ClientAuthPolicy $policy, string $issuer, string $endpoint): ?ClientData
    {
        $method = $credentials->method();
        if ($method === self::PRIVATE_KEY_JWT) {
            return $this->assertion($tenant, $credentials, $issuer, $endpoint);
        }
        $clientId = $credentials->clientId();
        if ($method === null || $clientId === null) {
            return null;
        }
        if ($policy === ClientAuthPolicy::RegisteredMethod
            && $this->clients->findEnabledClient($tenant, $clientId)?->tokenEndpointAuthMethod !== $method
        ) {
            return null;
        }
        $client = $method === 'none'
            ? ($policy === ClientAuthPolicy::Secret ? null : $this->clients->preValidatedClient($tenant, $clientId))
            : $this->clients->clientData($tenant, $clientId, $credentials->secret());
        if ($client === null || $client->id !== $clientId || $client->tokenEndpointAuthMethod === self::PRIVATE_KEY_JWT) {
            return null;
        }
        return $client;
    }

    private function assertion(string $tenant, ClientCredentials $credentials, string $issuer, string $endpoint): ?ClientData
    {
        $assertion = $credentials->assertion;
        if ($credentials->assertionType !== ClientCredentials::ASSERTION_TYPE || $assertion === null) {
            return null;
        }
        $clientId = $credentials->clientId() ?? JwtAssertionVerifier::unverifiedIssuer($assertion);
        $client = $clientId !== null ? $this->clients->findEnabledClient($tenant, $clientId) : null;
        if ($client === null || $client->id !== $clientId || $client->tokenEndpointAuthMethod !== self::PRIVATE_KEY_JWT) {
            return null;
        }
        try {
            $claims = $this->assertions->verify(
                $assertion,
                $client,
                $tenant,
                ReplayPurpose::ClientAssertion,
                algorithm: $client->tokenEndpointAuthSigningAlg,
                audiences: [$issuer, "{$issuer}/token", $endpoint],
                subjectIsClient: true,
            );
            if (array_key_exists('response_type', $claims)) {
                throw new \InvalidArgumentException('an authorization request object is not a client assertion');
            }
        } catch (\InvalidArgumentException $ex) {
            $this->logWarning('Rejected client assertion', ['client-id' => $clientId, 'reason' => $ex->getMessage()]);
            return null;
        }
        return $client;
    }
}
