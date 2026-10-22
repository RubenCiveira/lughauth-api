<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Response;
use Slim\Psr7\Factory\ServerRequestFactory;
use Civi\Lughauth\Shared\Context;
use Civi\Lughauth\Shared\Exception\UnauthorizedException;
use Civi\Lughauth\Shared\Security\Identity;
use Civi\Lughauth\Features\Access\Tenant\Domain\Gateway\TenantQueryRepositoryGateway;
use Civi\Lughauth\Features\Access\TenantConfig\Domain\Gateway\TenantConfigQueryRepositoryGateway;
use Civi\Lughauth\Features\OAuth\WebAuthn\Application\Usecase\BeginAuthentication\BeginAuthenticationUsecase;
use Civi\Lughauth\Features\OAuth\WebAuthn\Application\Usecase\BeginRegistration\BeginRegistrationUsecase;
use Civi\Lughauth\Features\OAuth\WebAuthn\Application\Usecase\FinishAuthentication\FinishAuthenticationUsecase;
use Civi\Lughauth\Features\OAuth\WebAuthn\Application\Usecase\FinishRegistration\FinishRegistrationUsecase;
use Civi\Lughauth\Features\OAuth\WebAuthn\Infrastructure\Driver\Rest\WebAuthnController;

/**
 * Registering a credential on a tenant route needs a token of that tenant's own issuer: the JWT
 * middleware trusts any issuer under its root and does not look at the tenant of the route.
 */
final class WebAuthnControllerUnitTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function registrationSteps(): array
    {
        return ['begin' => ['registerBegin'], 'finish' => ['registerFinish']];
    }

    #[DataProvider('registrationSteps')]
    public function testRejectsATokenOfAnotherTenant(string $step): void
    {
        $identity = $this->createStub(Identity::class);
        $identity->method('isAnonymous')->willReturn(false);
        $identity->method('getIssuer')->willReturn('https://idp/oauth/openid/other');
        $context = $this->createStub(Context::class);
        $context->method('getIdentity')->willReturn($identity);
        $context->method('getIssuer')->willReturnCallback(fn (string $tenant): string => 'https://idp/oauth/openid/' . $tenant);
        $controller = new WebAuthnController(
            $context,
            $this->createStub(TenantQueryRepositoryGateway::class),
            $this->createStub(TenantConfigQueryRepositoryGateway::class),
            // Never reached: the request is rejected before any ceremony starts.
            (new ReflectionClass(BeginRegistrationUsecase::class))->newInstanceWithoutConstructor(),
            (new ReflectionClass(FinishRegistrationUsecase::class))->newInstanceWithoutConstructor(),
            (new ReflectionClass(BeginAuthenticationUsecase::class))->newInstanceWithoutConstructor(),
            (new ReflectionClass(FinishAuthenticationUsecase::class))->newInstanceWithoutConstructor(),
        );

        $this->expectException(UnauthorizedException::class);

        $controller->{$step}((new ServerRequestFactory())->createServerRequest('POST', '/'), new Response(), ['tenant' => 'acme']);
    }
}
