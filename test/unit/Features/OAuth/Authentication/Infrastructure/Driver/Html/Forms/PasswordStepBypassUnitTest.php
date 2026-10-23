<?php

declare(strict_types=1);

require_once __DIR__ . '/FormsTestCase.php';

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\EventDispatcher\EventDispatcherInterface;
use Civi\Lughauth\Features\OAuth\Authentication\Application\AuthenticateUser;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationResult;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception\LoginException;
use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driver\Html\Forms\LoginForm;
use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driver\Html\Forms\NewMfaForm;
use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driver\Html\Forms\ScopeConsentForm;
use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driver\Html\Forms\StepForm;
use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driver\Html\Forms\UseMfaForm;
use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driver\Html\Services\HtmlSecurer;
use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driver\Html\Services\PublicMfa;
use Civi\Lughauth\Features\OAuth\Consent\Application\Usecase\ScopesConsentUsecase;
use Civi\Lughauth\Features\OAuth\DelegateLogin\Application\DelegateLogin;
use Civi\Lughauth\Features\OAuth\MagicLink\Application\Usecase\RequestMagicLink\RequestMagicLinkUsecase;
use Civi\Lughauth\Features\OAuth\Session\Domain\Gateway\SessionStoreGateway;
use Civi\Lughauth\Features\OAuth\Theme\Application\DecorateHtml;
use Civi\Lughauth\Features\OAuth\User\Application\Usecase\ChangePasswordUsecase;
use Civi\Lughauth\Features\OAuth\User\Application\Usecase\RegisterUserUsecase;
use Civi\Lughauth\Features\OAuth\User\Domain\Gateway\LoginGateway;
use Civi\Lughauth\Shared\Infrastructure\Translation\MessageProvider;

/**
 * A wrong password keeps the typed username in the pre-session state. Posting the next step
 * directly (step=mfa with a valid code, or step=scopes-consent) must not open a session for it.
 * A right password must not let step=build-mfa replace an MFA that is already active.
 */
#[AllowMockObjectsWithoutExpectations]
final class PasswordStepBypassUnitTest extends FormsTestCase
{
    public static function nextSteps(): array
    {
        return [
            'mfa with a valid totp' => ['mfa', ['mfa_code' => '123456']],
            'mfa with a valid recovery code' => ['mfa', ['recovery_code' => 'A1B2C3D4E5']],
            'scopes consent with nothing pending' => ['scopes-consent', []],
        ];
    }

    #[DataProvider('nextSteps')]
    public function testAWrongPasswordFollowedByTheNextStepOpensNoSession(string $step, array $body): void
    {
        $login = $this->createMock(LoginGateway::class);
        $login->method('validatedUserData')->willThrowException(
            new LoginException(auth: AuthenticationResult::wrongCredentials('tenant1', 'victim'))
        );
        // The victim is valid as soon as someone asks for it: only the missing proof stops the flow.
        $login->method('fillPreAuthenticated')->willReturn(new AuthenticationResult(valid: true, id: 'victim', tenant: 'tenant1'));
        $sessions = $this->createMock(SessionStoreGateway::class);
        $sessions->expects($this->never())->method('saveSession');
        $authenticator = new AuthenticateUser($this->createMock(EventDispatcherInterface::class), $login, $sessions);

        $stored = $this->wrongPassword($authenticator);

        $this->expectException(LoginException::class);
        $this->form($step, $authenticator)->authenticate($this->buildInput($body, $stored));
    }

    public function testARightPasswordCannotReplaceAnActiveMfaThroughEnrolment(): void
    {
        $login = $this->createMock(LoginGateway::class);
        $login->method('validatedUserData')->willReturn(AuthenticationResult::mfaRequired());
        $login->method('mfaEnrolmentPending')->willReturn(false);
        $login->method('fillPreAuthenticated')->willReturn(new AuthenticationResult(valid: true, id: 'victim', tenant: 'tenant1'));
        $sessions = $this->createMock(SessionStoreGateway::class);
        $sessions->expects($this->never())->method('saveSession');
        $authenticator = new AuthenticateUser($this->createMock(EventDispatcherInterface::class), $login, $sessions);
        $stored = $this->login($authenticator, 'right-password');
        $this->assertTrue($stored->isIdentified());

        $publicMfa = $this->createMock(PublicMfa::class);
        $publicMfa->expects($this->never())->method('verifyNewOpt');
        $securer = $this->createMock(HtmlSecurer::class);
        $securer->method('decrypt')->willReturn('attacker-seed');
        $enrolment = new NewMfaForm($this->createMock(MessageProvider::class), $authenticator, $publicMfa, $this->createMock(DecorateHtml::class), $securer);

        $this->expectException(LoginException::class);
        $enrolment->authenticate($this->buildInput(['seed' => 'enc-seed', 'mfa_code' => '123456'], $stored));
    }

    /** Runs the login step with a wrong password and returns the state kept for the next request. */
    private function wrongPassword(AuthenticateUser $authenticator): ChallengesState
    {
        $stored = $this->login($authenticator, 'wrong-password');
        $this->assertFalse($stored->isIdentified());
        return $stored;
    }

    /** Runs the login step, which must stop with a pending step, and returns the state kept for the next request. */
    private function login(AuthenticateUser $authenticator, string $password): ChallengesState
    {
        $securer = $this->createMock(HtmlSecurer::class);
        $securer->method('decrypt')->willReturn($password);
        $loginForm = new LoginForm(
            $this->createMock(MessageProvider::class),
            $authenticator,
            $this->createMock(DecorateHtml::class),
            $securer,
            $this->createMock(DelegateLogin::class),
            $this->createMock(ChangePasswordUsecase::class),
            $this->createMock(RegisterUserUsecase::class),
            $this->createMock(RequestMagicLinkUsecase::class),
        );
        try {
            $loginForm->authenticate($this->buildInput(['username' => 'victim', 'password' => 'enc'], new ChallengesState()));
        } catch (LoginException $ex) {
            $this->assertSame('victim', $ex->challenges?->username);
            // AuthorizeHtml keeps it in the pre-session store between requests.
            return ChallengesState::fromArray(($ex->challenges ?? new ChallengesState())->toArray());
        }
        $this->fail('The login step must stop before authenticating');
    }

    private function form(string $step, AuthenticateUser $authenticator): StepForm
    {
        if ($step === 'mfa') {
            $publicMfa = $this->createMock(PublicMfa::class);
            $publicMfa->method('verifyOtp')->willReturn(true);
            $publicMfa->method('consumeRecoveryCode')->willReturn(true);
            return new UseMfaForm(
                $this->createMock(MessageProvider::class),
                $authenticator,
                $publicMfa,
                $this->createMock(DecorateHtml::class),
                $this->createMock(HtmlSecurer::class)
            );
        }
        $consent = $this->createMock(ScopesConsentUsecase::class);
        $consent->method('pendingScopes')->willReturn([]);
        return new ScopeConsentForm(
            $this->createMock(MessageProvider::class),
            $authenticator,
            $consent,
            $this->createMock(DecorateHtml::class),
            $this->createMock(HtmlSecurer::class)
        );
    }
}
