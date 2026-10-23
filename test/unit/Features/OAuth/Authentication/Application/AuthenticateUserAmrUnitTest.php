<?php

declare(strict_types=1);

use Civi\Lughauth\Features\OAuth\Authentication\Application\AuthenticateUser;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationRequest;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception\LoginException;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\IdentificationMethod;
use Civi\Lughauth\Features\OAuth\Client\Domain\ClientData;
use Civi\Lughauth\Features\OAuth\Session\Domain\Gateway\SessionStoreGateway;
use Civi\Lughauth\Features\OAuth\User\Domain\Gateway\LoginGateway;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * preAuthenticate only continues a flow whose user was identified, and the amr saved with the new
 * session names the methods actually used: the password checked on the login step and the second factor.
 */
#[AllowMockObjectsWithoutExpectations]
final class AuthenticateUserAmrUnitTest extends TestCase
{
    public static function secondFactors(): array
    {
        return [
            'password and totp' => [self::byPassword()->withMfa(true, 'otp'), ['pwd', 'otp', 'mfa']],
            'password and recovery code' => [self::byPassword()->withMfa(true), ['pwd', 'mfa']],
            'password only' => [self::byPassword(), ['pwd']],
            'webauthn' => [(new ChallengesState(username: 'user-1'))->identifiedBy(IdentificationMethod::WEBAUTHN), []],
        ];
    }

    #[DataProvider('secondFactors')]
    public function testPreAuthenticatedSessionRecordsTheSecondFactor(ChallengesState $challenges, array $amr): void
    {
        $saved = null;
        $sessions = $this->createMock(SessionStoreGateway::class);
        $sessions->method('saveSession')->willReturnCallback(
            function (...$args) use (&$saved): void {
                $saved = $args[7];
            }
        );

        $this->preAuthenticate($challenges, $sessions);

        $this->assertSame($amr, $saved);
    }

    public function testAUsernameThatWasOnlyTypedIsNotAuthenticated(): void
    {
        $sessions = $this->createMock(SessionStoreGateway::class);
        $sessions->expects($this->never())->method('saveSession');

        $this->expectException(LoginException::class);

        $this->preAuthenticate(new ChallengesState(username: 'user-1'), $sessions);
    }

    public function testDeclaringAnotherUserDiscardsTheProofGathered(): void
    {
        $state = self::byPassword()->withMfa(true, 'otp')->withUsername('user-2');

        $this->assertSame([false, false, null], [$state->isIdentified(), $state->withMfa, $state->mfaMethod]);
    }

    public function testTheProofSurvivesTheSessionRoundTrip(): void
    {
        $state = ChallengesState::fromArray(self::byPassword()->withMfa(true, 'otp')->toArray());

        $this->assertSame([IdentificationMethod::PASSWORD, 'otp'], [$state->identifiedBy, $state->mfaMethod]);
    }

    private static function byPassword(): ChallengesState
    {
        return (new ChallengesState(username: 'user-1'))->identifiedBy(IdentificationMethod::PASSWORD);
    }

    private function preAuthenticate(ChallengesState $challenges, SessionStoreGateway $sessions): void
    {
        $login = $this->createMock(LoginGateway::class);
        $login->method('fillPreAuthenticated')->willReturn(new AuthenticationResult(valid: true, id: 'user-1', tenant: 'tenant1'));

        (new AuthenticateUser($this->createMock(EventDispatcherInterface::class), $login, $sessions))->preAuthenticate(
            new AuthenticationRequest(
                client: new ClientData('client-123', ['code'], true),
                scope: 'openid',
                redirect: 'https://client.example/callback',
                responseType: 'code',
                audiences: ['client-123']
            ),
            $challenges,
            'tenant1',
            'https://issuer.example/tenant1',
            'csid-123',
            'state',
            'nonce'
        );
    }
}
