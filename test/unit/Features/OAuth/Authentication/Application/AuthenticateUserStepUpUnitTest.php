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
use Civi\Lughauth\Features\OAuth\Session\Domain\SessionInfo;
use Civi\Lughauth\Features\OAuth\User\Domain\Gateway\LoginGateway;
use Civi\Lughauth\Shared\Security\Acr;
use Civi\Lughauth\Shared\Exception\UnauthorizedException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Nothing is issued below the acr the client requested (task 067): until the user proves the
 * second factor the flow asks for it (or its enrolment), and a step-up adds that factor to the
 * methods of the session; without the factor, the session identification is no login.
 */
#[AllowMockObjectsWithoutExpectations]
final class AuthenticateUserStepUpUnitTest extends TestCase
{
    private const string ISSUER = 'https://issuer.example/tenant1';

    public function testAPasswordAloneIsAskedForTheEnrolledTotp(): void
    {
        $sessions = $this->createMock(SessionStoreGateway::class);
        $sessions->expects($this->never())->method('saveSession');

        $ex = $this->failedPreAuthenticate(self::byPassword(), Acr::MULTI_FACTOR, $sessions, seed: true);

        $this->assertSame(AuthenticationResult::ERR_MFA_REQUIRED, $ex->auth->error);
        $this->assertSame([IdentificationMethod::PASSWORD, 'alice'], [$ex->challenges?->identifiedBy, $ex->challenges?->username]);
    }

    public function testAUserWithoutSeedIsAskedToEnrolOne(): void
    {
        $ex = $this->failedPreAuthenticate(self::byPassword(), Acr::MULTI_FACTOR, $this->createMock(SessionStoreGateway::class), seed: false);

        $this->assertSame(AuthenticationResult::ERR_NEW_MFA_REQUIRED, $ex->auth->error);
    }

    public function testTheSecondFactorReachesTheRequestedLevel(): void
    {
        $saved = null;
        $sessions = $this->createMock(SessionStoreGateway::class);
        $sessions->method('saveSession')->willReturnCallback(function (...$args) use (&$saved): void {
            $saved = $args[7];
        });

        $this->preAuthenticate(self::byPassword()->withMfa(true, 'otp'), Acr::MULTI_FACTOR, $sessions);

        $this->assertSame(['pwd', 'otp', 'mfa'], $saved);
    }

    public function testASingleFactorRequestNeedsNoSecondFactor(): void
    {
        $sessions = $this->createMock(SessionStoreGateway::class);
        $sessions->expects($this->once())->method('saveSession');

        $this->preAuthenticate(self::byPassword(), Acr::SINGLE_FACTOR, $sessions);
    }

    /**
     * The step-up elevates the session that started it, found by its public sid, even when the
     * browser's csid now points to another session.
     */
    public function testAStepUpAddsTheFactorToThoseOfTheSessionThatStartedIt(): void
    {
        $sessions = $this->existingSession(['pwd'], csidSession: 'session-of-the-browser-now');
        $sessions->expects($this->once())->method('renewAuthentication')->with('session-1', self::ISSUER, ['pwd', 'otp', 'mfa']);
        $sessions->expects($this->once())->method('addSessionClient')->with('session-1', self::ISSUER, 'client-123');
        $sessions->expects($this->never())->method('saveSession');

        $this->preAuthenticate(self::bySession()->withMfa(true, 'otp'), Acr::MULTI_FACTOR, $sessions);
    }

    public static function lostSessions(): array
    {
        return [
            'closed or expired since the step-up started' => [null, 'user-1'],
            'now of another user' => ['session-1', 'user-2'],
        ];
    }

    /**
     * A step-up whose session is gone proves nothing: the second factor neither opens a new MFA
     * session nor elevates the session the browser has now.
     */
    #[DataProvider('lostSessions')]
    public function testAStepUpWhoseSessionIsGoneIsRejected(?string $sessionId, string $owner): void
    {
        $sessions = $this->createMock(SessionStoreGateway::class);
        $sessions->expects($this->once())->method('findActiveSessionIdBySid')->with('sid-1', self::ISSUER)->willReturn($sessionId);
        $sessions->method('findActiveSessionIdByCsid')->willReturn('session-of-the-browser-now');
        $sessions->method('findActiveSession')->willReturn(new SessionInfo('csid-123', false, self::ISSUER, $owner, 'client-123', amr: ['pwd']));
        $sessions->expects($this->never())->method('saveSession');
        $sessions->expects($this->never())->method('renewAuthentication');
        $sessions->expects($this->never())->method('addSessionClient');

        $this->expectException(UnauthorizedException::class);

        $this->preAuthenticate(self::bySession()->withMfa(true, 'otp'), Acr::MULTI_FACTOR, $sessions);
    }

    public function testAStepUpThatDoesNotNameItsSessionIsRejected(): void
    {
        $sessions = $this->existingSession(['pwd']);
        $sessions->expects($this->never())->method('saveSession');
        $sessions->expects($this->never())->method('renewAuthentication');

        $this->expectException(UnauthorizedException::class);

        $this->preAuthenticate(self::bySession()->withExtra(AuthenticateUser::STEP_UP_SID, null)->withMfa(true, 'otp'), Acr::MULTI_FACTOR, $sessions);
    }

    public function testAStepUpWithoutTheFactorIsAskedForItAgain(): void
    {
        $sessions = $this->existingSession(['pwd']);
        $sessions->expects($this->never())->method('renewAuthentication');

        $ex = $this->failedPreAuthenticate(self::bySession(), Acr::MULTI_FACTOR, $sessions, seed: true);

        $this->assertSame(IdentificationMethod::SESSION, $ex->challenges?->identifiedBy);
    }

    /**
     * A step-up left half way (no second factor) and replayed without acr_values is no login: it
     * cannot issue anything, nor move the auth_time of the session.
     */
    public function testASessionIdentificationWithoutTheFactorIsNoLogin(): void
    {
        $sessions = $this->existingSession(['pwd']);
        $sessions->expects($this->never())->method('renewAuthentication');
        $sessions->expects($this->never())->method('addSessionClient');
        $sessions->expects($this->never())->method('saveSession');

        $this->expectException(UnauthorizedException::class);

        $this->preAuthenticate(self::bySession(), null, $sessions);
    }

    private static function byPassword(): ChallengesState
    {
        return (new ChallengesState(username: 'alice'))->identifiedBy(IdentificationMethod::PASSWORD);
    }

    private static function bySession(): ChallengesState
    {
        return (new ChallengesState(username: 'alice'))
            ->identifiedBy(IdentificationMethod::SESSION)
            ->withExtra(AuthenticateUser::STEP_UP_SID, 'sid-1');
    }

    /** @param string[] $amr */
    private function existingSession(array $amr, string $csidSession = 'session-1'): SessionStoreGateway
    {
        $sessions = $this->createMock(SessionStoreGateway::class);
        $sessions->method('findActiveSessionIdBySid')->willReturnMap([['sid-1', self::ISSUER, 'session-1']]);
        $sessions->method('findActiveSessionIdByCsid')->willReturn($csidSession);
        $sessions->method('findActiveSession')->willReturn(
            new SessionInfo('csid-123', false, self::ISSUER, 'user-1', 'client-123', amr: $amr)
        );
        return $sessions;
    }

    private function failedPreAuthenticate(ChallengesState $challenges, ?Acr $required, SessionStoreGateway $sessions, bool $seed): LoginException
    {
        try {
            $this->preAuthenticate($challenges, $required, $sessions, $seed);
        } catch (LoginException $ex) {
            return $ex;
        }
        $this->fail('The flow must ask for the second factor');
    }

    private function preAuthenticate(ChallengesState $challenges, ?Acr $required, SessionStoreGateway $sessions, bool $seed = true): void
    {
        $login = $this->createMock(LoginGateway::class);
        $login->method('fillPreAuthenticated')->willReturn(new AuthenticationResult(valid: true, id: 'user-1', tenant: 'tenant1'));
        $login->method('usernameOf')->willReturn('alice');
        $login->method('secondFactorChallenge')->willReturn($seed ? AuthenticationResult::mfaRequired() : AuthenticationResult::newMfaRequired());

        (new AuthenticateUser($this->createMock(EventDispatcherInterface::class), $login, $sessions))->preAuthenticate(
            new AuthenticationRequest(
                client: new ClientData('client-123', ['code'], true),
                scope: 'openid',
                redirect: 'https://client.example/callback',
                responseType: 'code',
                audiences: ['client-123'],
                requiredAcr: $required
            ),
            $challenges,
            'tenant1',
            self::ISSUER,
            'csid-123',
            'state',
            'nonce'
        );
    }
}
