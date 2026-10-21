<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\AuthenticationRequest;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject\ScopeSet;
use Civi\Lughauth\Features\OAuth\Client\Domain\ClientData;

final class ScopeSetUnitTest extends TestCase
{
    public function test_values_are_exact_tokens(): void
    {
        $scope = ScopeSet::fromString('openid myprofile');

        $this->assertTrue($scope->has('myprofile'));
        $this->assertFalse($scope->has('profile'));
    }

    public function test_normalises_whitespace_and_duplicates_keeping_order(): void
    {
        $this->assertSame('openid email profile', ScopeSet::fromString("  openid\temail  openid\nprofile ")->toString());
        $this->assertTrue(ScopeSet::fromString(null)->isEmpty());
    }

    public function test_subset(): void
    {
        $granted = ScopeSet::fromString('openid profile email');

        $this->assertTrue(ScopeSet::fromString('email openid')->isSubsetOf($granted));
        $this->assertFalse(ScopeSet::fromString('openid admin')->isSubsetOf($granted));
    }

    public function test_authentication_request_normalises_the_scope_for_every_grant(): void
    {
        $request = new AuthenticationRequest(new ClientData('c', [], false), ' openid  profile openid ', '');

        $this->assertSame('openid profile', $request->scope);
    }
}
