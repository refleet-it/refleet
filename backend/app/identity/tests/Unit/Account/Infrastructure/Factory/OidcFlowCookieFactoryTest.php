<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Factory;

use App\Identity\Account\Infrastructure\Factory\OidcFlowCookieFactory;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;

#[CoversClass(OidcFlowCookieFactory::class)]
final class OidcFlowCookieFactoryTest extends TestCase
{
    #[Test]
    public function pins_the_state_into_a_cookie_no_script_can_read(): void
    {
        // The cookie is the half of the flow an attacker cannot write, so httpOnly and a path
        // narrowed to the callback are what it is for, not incidental hardening.
        // Act
        $cookie = new OidcFlowCookieFactory('prod')->create('signed-state');

        // Assert
        Assert::assertSame('oidc_flow', $cookie->getName());
        Assert::assertSame('signed-state', $cookie->getValue());
        Assert::assertSame('/api/identity/auth/oidc', $cookie->getPath());
        Assert::assertTrue($cookie->isHttpOnly());
        Assert::assertTrue($cookie->isSecure());
        Assert::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        Assert::assertGreaterThan(\time(), $cookie->getExpiresTime());
    }

    #[Test]
    public function serves_an_insecure_cookie_outside_production_so_local_http_works(): void
    {
        Assert::assertFalse(new OidcFlowCookieFactory('dev')->create('signed-state')->isSecure());
    }

    #[Test]
    public function clears_the_cookie_with_an_expiry_in_the_past(): void
    {
        // Act
        $cookie = new OidcFlowCookieFactory('prod')->createClear();

        // Assert
        Assert::assertSame('oidc_flow', $cookie->getName());
        Assert::assertSame('', $cookie->getValue());
        Assert::assertLessThan(\time(), $cookie->getExpiresTime());
        Assert::assertSame('/api/identity/auth/oidc', $cookie->getPath());
    }
}
