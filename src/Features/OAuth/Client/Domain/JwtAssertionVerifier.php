<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Client\Domain;

use Civi\Lughauth\Features\OAuth\Authentication\Domain\Gateway\RemoteJwksFetcher;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\ReplayJtiGateway;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\ReplayPurpose;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\Algorithm\PS256;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Signature\Serializer\CompactSerializer;

/**
 * Verifies a one-time JWT signed by a client with its registered keys: the JAR request object
 * (RFC 9101) and the private_key_jwt client assertion (RFC 7523 §3, task 044) follow the same
 * rules and differ only in their purpose, audience and subject.
 *
 * The JWT must be signed with an asymmetric algorithm (never "none" nor a shared secret), with the
 * client's registered algorithm when it has one, by a key of its inline JWKS or of its jwks_uri
 * (downloaded through RemoteJwksFetcher). Its iss (and, when asked, sub) must be the client, its
 * aud must name exactly one of the accepted audiences, exp is required and at most one hour away,
 * nbf and iat cannot be in the future, and its jti is consumed in the replay store under the given
 * purpose. A JWT signed by the client is single use across all its uses (CLIENT_SIGNED_PURPOSES):
 * its jti is also recorded under the other ones, so a request object cannot later authenticate the
 * client, nor a client assertion serve as a request object.
 *
 * Every failure throws InvalidArgumentException whose message names the failed check; callers map
 * it to their own OAuth error (invalid_request_object, invalid_client).
 */
final class JwtAssertionVerifier
{
    /** Signing algorithms accepted for client-signed JWTs. */
    public const array ALGORITHMS = ['RS256', 'ES256', 'PS256'];
    /** Uses of a JWT signed with the client keys, which share each JWT's single use. */
    private const array CLIENT_SIGNED_PURPOSES = [ReplayPurpose::RequestObject, ReplayPurpose::ClientAssertion];
    /** Clock skew tolerated between the client and this server. */
    private const int LEEWAY_SECONDS = 30;
    /** Longest lifetime accepted, which also bounds how long a jti is kept. */
    private const int MAX_LIFETIME_SECONDS = 3600;

    public function __construct(
        private readonly RemoteJwksFetcher $jwks,
        private readonly ReplayJtiGateway $replays,
    ) {
    }

    /**
     * Verifies the JWT of the client and returns its claims.
     *
     * @param string|null $algorithm the client's registered algorithm, or null for any of ALGORITHMS
     * @param string[] $audiences values the aud claim may name
     * @param bool $subjectIsClient whether sub must also be the client (client assertions)
     * @throws \InvalidArgumentException naming the failed check
     */
    public function verify(
        string $jwt,
        ClientData $client,
        string $tenant,
        ReplayPurpose $purpose,
        ?string $algorithm,
        array $audiences,
        bool $subjectIsClient,
    ): array {
        try {
            $jws = (new CompactSerializer())->unserialize($jwt);
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException('malformed JWT');
        }
        $alg = $jws->getSignature(0)->getProtectedHeader()['alg'] ?? null;
        if (!in_array($alg, self::ALGORITHMS, true) || ($algorithm !== null && $algorithm !== '' && $algorithm !== $alg)) {
            throw new \InvalidArgumentException('signing algorithm not allowed');
        }
        $algorithms = new AlgorithmManager([match ($alg) {
            'RS256' => new RS256(),
            'ES256' => new ES256(),
            'PS256' => new PS256(),
        }]);
        if (!(new JWSVerifier($algorithms))->verifyWithKeySet($jws, $this->clientKeys($client, $tenant), 0)) {
            throw new \InvalidArgumentException('signature does not match the client keys');
        }
        $payload = json_decode($jws->getPayload() ?? '', true);
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('malformed JWT');
        }

        if (($payload['iss'] ?? null) !== $client->id || ($subjectIsClient && ($payload['sub'] ?? null) !== $client->id)) {
            throw new \InvalidArgumentException('iss or sub is not the client');
        }
        $aud = $payload['aud'] ?? null;
        $named = is_string($aud) ? [$aud] : (is_array($aud) ? array_filter($aud, 'is_string') : []);
        if (array_intersect($named, $audiences) === []) {
            throw new \InvalidArgumentException('aud does not name this server');
        }
        $expiresAt = $this->checkTimes($payload);
        $jti = $payload['jti'] ?? null;
        if (!is_string($jti) || $jti === '') {
            throw new \InvalidArgumentException('jti is required');
        }
        $uses = in_array($purpose, self::CLIENT_SIGNED_PURPOSES, true)
            ? [$purpose, ...array_filter(self::CLIENT_SIGNED_PURPOSES, static fn (ReplayPurpose $use): bool => $use !== $purpose)]
            : [$purpose];
        foreach ($uses as $use) {
            if (!$this->replays->consume($tenant, $client->id, $use, $jti, $expiresAt)) {
                throw new \InvalidArgumentException('jti already used');
            }
        }
        return $payload;
    }

    /**
     * The iss claim of a JWT, read without verifying it: only to find which client claims to have
     * signed it, whose keys then verify it.
     */
    public static function unverifiedIssuer(string $jwt): ?string
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true);
        $issuer = is_array($payload) ? ($payload['iss'] ?? null) : null;
        return is_string($issuer) && $issuer !== '' ? $issuer : null;
    }

    /**
     * Requires exp within the accepted lifetime and nbf/iat not in the future. Returns how long the
     * jti must be kept: while the JWT is still accepted, that is exp plus the leeway.
     */
    private function checkTimes(array $payload): \DateTimeImmutable
    {
        $now = time();
        $exp = $payload['exp'] ?? null;
        if (!is_int($exp) || $exp <= $now - self::LEEWAY_SECONDS || $exp > $now + self::MAX_LIFETIME_SECONDS) {
            throw new \InvalidArgumentException('exp is missing, past or too far away');
        }
        foreach (['nbf', 'iat'] as $claim) {
            $value = $payload[$claim] ?? null;
            if ($value !== null && (!is_int($value) || $value > $now + self::LEEWAY_SECONDS)) {
                throw new \InvalidArgumentException("{$claim} is in the future");
            }
        }
        return (new \DateTimeImmutable())->setTimestamp($exp + self::LEEWAY_SECONDS);
    }

    private function clientKeys(ClientData $client, string $tenant): JWKSet
    {
        if ($client->jwksJson !== null && $client->jwksJson !== '') {
            $decoded = json_decode($client->jwksJson, true);
        } elseif ($client->jwksUri !== null && $client->jwksUri !== '') {
            $decoded = $this->jwks->fetch($tenant, $client->id, $client->jwksUri);
        } else {
            throw new \InvalidArgumentException('the client has no registered keys');
        }
        $keys = is_array($decoded) ? ($decoded['keys'] ?? null) : null;
        if (!is_array($keys) || $keys === []) {
            throw new \InvalidArgumentException('the client JWKS has no keys');
        }
        $result = [];
        foreach ($keys as $key) {
            if (!is_array($key)) {
                throw new \InvalidArgumentException('the client JWKS is malformed');
            }
            $result[] = new JWK($key);
        }
        return new JWKSet($result);
    }
}
