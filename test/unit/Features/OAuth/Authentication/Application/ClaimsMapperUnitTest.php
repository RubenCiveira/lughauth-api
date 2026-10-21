<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Civi\Lughauth\Features\OAuth\Authentication\Application\ClaimsMapper;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\TokenGrant;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject\ScopeSet;
use Civi\Lughauth\Features\OAuth\Client\Domain\ClientData;
use Civi\Lughauth\Features\OAuth\Session\Domain\SessionInfo;

final class ClaimsMapperUnitTest extends TestCase
{
    private ClaimsMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ClaimsMapper();
    }

    public function test_access_token_follows_rfc9068_and_addresses_client_and_resources(): void
    {
        $claims = $this->mapper->access($this->grant(audiences: ['client', 'api']));

        $this->assertSame('https://iss/t', $claims['iss']);
        $this->assertSame('user-1', $claims['sub']);
        $this->assertSame(['client', 'api'], $claims['aud']);
        $this->assertSame('client', $claims['client_id']);
        $this->assertSame('openid profile', $claims['scope']);
        $this->assertArrayNotHasKey('token_use', $claims);
    }

    public function test_access_token_for_client_only_has_a_string_audience(): void
    {
        $this->assertSame('client', $this->mapper->access($this->grant())['aud']);
    }

    public function test_client_credentials_access_token_is_marked(): void
    {
        $this->assertSame('client_credentials', $this->mapper->access($this->grant(grantType: 'client_credentials'))['token_use']);
    }

    public function test_id_token_is_addressed_only_to_the_client_and_carries_session_evidence(): void
    {
        $authTime = new DateTimeImmutable('@1700000000');
        $session = new SessionInfo('csid', false, 'https://iss/t', 'user-1', 'client', publicSid: 'public-sid', authTime: $authTime, amr: ['pwd']);

        $claims = $this->mapper->id($this->grant(audiences: ['client', 'api'], session: $session, nonce: 'n-1'), 'access', 'code', 'state');

        $this->assertSame('client', $claims['aud']);
        $this->assertSame(1700000000, $claims['auth_time']);
        $this->assertSame(['pwd'], $claims['amr']);
        $this->assertSame('public-sid', $claims['sid']);
        $this->assertSame('n-1', $claims['nonce']);
        $this->assertSame(ClaimsMapper::hash('access'), $claims['at_hash']);
        $this->assertSame(ClaimsMapper::hash('code'), $claims['c_hash']);
        $this->assertSame(ClaimsMapper::hash('state'), $claims['s_hash']);
        $this->assertArrayNotHasKey('acr', $claims);
    }

    public function test_id_token_without_session_or_nonce_invents_nothing(): void
    {
        $claims = $this->mapper->id($this->grant(nonce: ''));

        foreach (['auth_time', 'amr', 'acr', 'sid', 'nonce', 'at_hash', 'c_hash', 's_hash'] as $claim) {
            $this->assertArrayNotHasKey($claim, $claims, $claim);
        }
    }

    public function test_profile_claims_need_the_exact_scope_value(): void
    {
        $exact = $this->mapper->id($this->grant(scope: 'openid profile email'));
        $lookalike = $this->mapper->id($this->grant(scope: 'openid myprofile email_verified'));

        $this->assertSame('Alice', $exact['name']);
        $this->assertSame('alice@example.com', $exact['email']);
        $this->assertArrayNotHasKey('name', $lookalike);
        $this->assertArrayNotHasKey('email', $lookalike);
    }

    public function test_refresh_token_keeps_the_original_scope_and_the_public_sid(): void
    {
        $session = new SessionInfo('csid', false, 'https://iss/t', 'user-1', 'client', publicSid: 'public-sid');
        $grant = $this->grant(audiences: ['client', 'api'], session: $session, refreshScope: ScopeSet::fromString('openid profile email'));

        $claims = $this->mapper->refresh($grant, ['fid' => 'fam', 'fexp' => 2000000000], 1900000000);

        $this->assertSame('openid profile email', $claims['scope']);
        $this->assertSame(['api'], $claims['resources']);
        $this->assertSame('public-sid', $claims['sid']);
        $this->assertSame('refresh', $claims['token_use']);
        $this->assertSame(1900000000, $claims['exp']);
    }

    public function test_hash_is_the_left_half_of_sha256_in_base64url(): void
    {
        // Example of OpenID Connect Core 1.0 §A.3 (at_hash of its access token).
        $this->assertSame('77QmUPtjPfzWtF2AnpK9RQ', ClaimsMapper::hash('jHkWEdUXMU1BwAsC4vtUsZwnNvTIxEl0z9K3vx5KF0Y'));
    }

    /** @param string[] $audiences */
    private function grant(
        string $grantType = 'authorization_code',
        string $scope = 'openid profile',
        array $audiences = [],
        ?SessionInfo $session = null,
        ?string $nonce = null,
        ?ScopeSet $refreshScope = null,
    ): TokenGrant {
        return new TokenGrant(
            tenant: 't',
            issuer: 'https://iss/t',
            client: new ClientData('client', ['authorization_code', 'refresh_token'], true),
            grantType: $grantType,
            auth: new AuthenticationResult(valid: true, id: 'user-1', tenant: 't-uid', name: 'Alice', email: 'alice@example.com'),
            scope: ScopeSet::fromString($scope),
            audiences: $audiences,
            session: $session,
            nonce: $nonce,
            refreshScope: $refreshScope,
        );
    }
}
