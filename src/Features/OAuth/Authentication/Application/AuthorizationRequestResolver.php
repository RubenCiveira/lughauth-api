<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Application;

use Civi\Lughauth\Shared\Exception\UnauthorizedException;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\RequestObjectValidator;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ResolvedAuthorizationRequest;
use Civi\Lughauth\Features\OAuth\Client\Domain\Gateway\ClientStoreGateway;
use Civi\Lughauth\Features\OAuth\Par\Application\Usecase\ResolveParRequest\ResolveParRequestUsecase;
use Civi\Lughauth\Features\OAuth\Par\Domain\Exception\ParException;
use Civi\Lughauth\Features\OAuth\Par\Domain\Gateway\TenantParPolicyGateway;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\ReplayJtiGateway;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\Gateway\TokenSigner;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\ReplayPurpose;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\TokenType;
use Civi\Lughauth\Features\OAuth\TokenSecurity\Domain\VerificationPolicy;

/**
 * Decides, once, where the parameters of an /authorize request come from and what they are worth.
 *
 * - `request_uri` (PAR, RFC 9126): only the client_id of the query is read, and it must be the
 *   client that pushed the request; every other query parameter is ignored. A pushed request
 *   may itself carry a request object, which is then verified.
 * - `request` (JAR, RFC 9101): only the verified claims are used, never the query.
 * - `request_ref`: the signed reference this class issues after consuming a PAR or JAR request,
 *   so the following login steps (which post back to their own URL) keep its parameters. It can
 *   be presented at every step, but only one authorization completes with it (complete()).
 * - otherwise, the query string itself.
 *
 * A client or tenant that requires PAR only accepts the first case (or a reference to it).
 * Failures throw UnauthorizedException with the OAuth error code as message.
 */
class AuthorizationRequestResolver
{
    /** Query parameter carrying the signed reference between login steps. */
    public const string REFERENCE_PARAM = ResolvedAuthorizationRequest::REFERENCE_PARAM;
    /** Covers the whole interactive login, including the e-mailed recovery and registration links. */
    private const string REFERENCE_LIFETIME = 'PT30M';
    /** JWT claims of a request object that are not authorization parameters. */
    private const array JWT_CLAIMS = ['iss', 'aud', 'exp', 'nbf', 'iat', 'jti'];

    public function __construct(
        private readonly ClientStoreGateway $clients,
        private readonly ResolveParRequestUsecase $pushedRequests,
        private readonly TenantParPolicyGateway $parPolicy,
        private readonly RequestObjectValidator $requestObjects,
        private readonly TokenSigner $signer,
        private readonly ReplayJtiGateway $replays,
    ) {
    }

    /**
     * Issues the authorization response of a reference only once: closes the reference
     * atomically, then runs $issue. Returns null, without issuing, when the reference already
     * completed an authorization (or is not valid), so a replayed URL cannot issue a second code.
     * When $issue throws, the reference is reopened, since nothing reached the client, and the
     * same step can be retried. A query request has no reference and always issues.
     *
     * @template T
     * @param callable(): T $issue
     * @return T|null
     */
    public function complete(string $tenant, ?string $reference, callable $issue): mixed
    {
        if ($reference === null) {
            return $issue();
        }
        $payload = $this->signer->verify($tenant, $reference, VerificationPolicy::expecting(TokenType::AuthorizationRequest));
        $jti = $payload['jti'] ?? null;
        $exp = $payload['exp'] ?? null;
        if (!is_string($jti) || !is_int($exp)) {
            return null;
        }
        $clientId = (string) $payload['client_id'];
        // The signer rejects the reference after exp, so the record is not needed any longer.
        $expiresAt = (new \DateTimeImmutable())->setTimestamp($exp);
        if (!$this->replays->consume($tenant, $clientId, ReplayPurpose::AuthorizationRequest, $jti, $expiresAt)) {
            return null;
        }
        try {
            return $issue();
        } catch (\Throwable $ex) {
            $this->replays->release($tenant, $clientId, ReplayPurpose::AuthorizationRequest, $jti);
            throw $ex;
        }
    }

    /**
     * Resolves the authorization parameters from the query of an /authorize request. The issuer
     * is the canonical issuer of the tenant, which a request object must name as audience.
     */
    public function resolve(string $tenant, string $issuer, array $query): ResolvedAuthorizationRequest
    {
        $clientId = (string) ($query['client_id'] ?? '');
        $reference = (string) ($query[self::REFERENCE_PARAM] ?? '');
        if ($reference !== '') {
            $resolved = $this->fromReference($tenant, $clientId, $reference);
            $this->rejectWhenParIsRequired($tenant, $clientId, $resolved->origin);
            return $resolved;
        }

        $requestUri = (string) ($query['request_uri'] ?? '');
        $requestObject = (string) ($query['request'] ?? '');
        if ($requestUri !== '' && $requestObject !== '') {
            throw new UnauthorizedException('invalid_request');
        }
        if ($requestUri !== '') {
            return $this->withReference($tenant, $clientId, ResolvedAuthorizationRequest::ORIGIN_PUSHED, $this->pushedParams($tenant, $issuer, $clientId, $requestUri));
        }

        $origin = $requestObject !== '' ? ResolvedAuthorizationRequest::ORIGIN_SIGNED : ResolvedAuthorizationRequest::ORIGIN_QUERY;
        // Checked before verifying a request object, so a rejected request does not spend its jti.
        $this->rejectWhenParIsRequired($tenant, $clientId, $origin);
        if ($requestObject !== '') {
            return $this->withReference($tenant, $clientId, $origin, $this->signedParams($tenant, $issuer, $clientId, $requestObject));
        }
        return new ResolvedAuthorizationRequest($query, $origin);
    }

    private function pushedParams(string $tenant, string $issuer, string $clientId, string $requestUri): array
    {
        try {
            $params = $this->pushedRequests->resolve($requestUri, $tenant, $clientId)->params;
        } catch (ParException) {
            throw new UnauthorizedException('invalid_request_uri');
        }
        $requestObject = (string) ($params['request'] ?? '');
        return $requestObject !== '' ? $this->signedParams($tenant, $issuer, $clientId, $requestObject) : $params;
    }

    private function signedParams(string $tenant, string $issuer, string $clientId, string $requestObject): array
    {
        $client = $this->clients->preValidatedClient($tenant, $clientId);
        if ($client === null) {
            throw new UnauthorizedException('invalid_request_object');
        }
        try {
            $claims = $this->requestObjects->validate($requestObject, $client, $tenant, $issuer);
        } catch (\InvalidArgumentException) {
            throw new UnauthorizedException('invalid_request_object');
        }
        return array_diff_key($claims, array_flip(self::JWT_CLAIMS));
    }

    private function withReference(string $tenant, string $clientId, string $origin, array $params): ResolvedAuthorizationRequest
    {
        $reference = $this->signer->sign(
            TokenType::AuthorizationRequest,
            ['client_id' => $clientId, 'origin' => $origin, 'params' => $params],
            $tenant,
            new \DateInterval(self::REFERENCE_LIFETIME)
        );
        return new ResolvedAuthorizationRequest($params, $origin, $reference, consumedNow: true);
    }

    private function fromReference(string $tenant, string $clientId, string $reference): ResolvedAuthorizationRequest
    {
        $payload = $this->signer->verify($tenant, $reference, VerificationPolicy::expecting(TokenType::AuthorizationRequest));
        $origin = $payload['origin'] ?? null;
        $params = $payload['params'] ?? null;
        if (($payload['client_id'] ?? null) !== $clientId
            || !in_array($origin, [ResolvedAuthorizationRequest::ORIGIN_PUSHED, ResolvedAuthorizationRequest::ORIGIN_SIGNED], true)
            || !is_array($params)
        ) {
            throw new UnauthorizedException('invalid_request');
        }
        return new ResolvedAuthorizationRequest($params, $origin, $reference);
    }

    private function rejectWhenParIsRequired(string $tenant, string $clientId, string $origin): void
    {
        if ($origin === ResolvedAuthorizationRequest::ORIGIN_PUSHED) {
            return;
        }
        $client = $this->clients->findEnabledClient($tenant, $clientId);
        if (($client?->requirePar ?? false) || $this->parPolicy->requiresPar($tenant)) {
            throw new UnauthorizedException('invalid_request');
        }
    }
}
