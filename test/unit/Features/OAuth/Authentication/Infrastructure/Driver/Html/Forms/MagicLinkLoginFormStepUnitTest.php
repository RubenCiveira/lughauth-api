<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ChallengesState;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\Exception\LoginException;
use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driver\Html\Forms\MagicLinkLoginForm;
use Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driver\Html\Services\HtmlSecurer;
use Civi\Lughauth\Features\OAuth\MagicLink\Application\Usecase\RequestMagicLink\RequestMagicLinkUsecase;
use Civi\Lughauth\Features\OAuth\Theme\Application\DecorateHtml;
use Civi\Lughauth\Shared\Exception\UnauthorizedException;
use Civi\Lughauth\Shared\Infrastructure\Translation\MessageProvider;

require_once __DIR__ . '/FormsTestCase.php';

/**
 * The magic link only answers with a code in the query, so it is only sent for such flows (EP-08).
 */
#[AllowMockObjectsWithoutExpectations]
final class MagicLinkLoginFormStepUnitTest extends FormsTestCase
{
    public static function flows(): array
    {
        return [
            'code in the query' => [['response_type' => 'code'], true],
            'code asked in the query' => [['response_type' => 'code', 'response_mode' => 'query'], true],
            'code by fragment' => [['response_type' => 'code', 'response_mode' => 'fragment'], false],
            'code by form_post' => [['response_type' => 'code', 'response_mode' => 'form_post'], false],
            'hybrid' => [['response_type' => 'code id_token'], false],
        ];
    }

    #[DataProvider('flows')]
    public function testTheLinkIsOnlySentWhenTheClientExpectsACodeInTheQuery(array $query, bool $sent): void
    {
        $requestMagicLink = $this->createMock(RequestMagicLinkUsecase::class);
        $requestMagicLink->expects($sent ? $this->once() : $this->never())->method('request');
        $form = new MagicLinkLoginForm(
            $this->createMock(MessageProvider::class),
            $requestMagicLink,
            $this->createMock(DecorateHtml::class),
            $this->createMock(HtmlSecurer::class),
        );

        $this->expectException($sent ? LoginException::class : UnauthorizedException::class);

        $form->authenticate($this->buildInput(['email' => 'user@example.com'], new ChallengesState(), $query));
    }
}
