<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Security;

use App\Shared\Infrastructure\Security\CorsConfigurationGuard;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(CorsConfigurationGuard::class)]
final class CorsConfigurationGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function insecureOriginProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'bare wildcard' => ['*'];
        yield 'match-any regex' => ['.*'];
        yield 'match-one-or-more regex' => ['.+'];
        yield 'anchored match-all regex' => ['^.*$'];
    }

    #[Test]
    #[DataProvider('insecureOriginProvider')]
    public function throws_in_prod_when_origin_is_insecure(string $corsAllowOrigin): void
    {
        $guard = new CorsConfigurationGuard('prod', $corsAllowOrigin);

        $this->expectException(\RuntimeException::class);

        $guard($this->createEvent());
    }

    #[Test]
    public function allows_a_specific_origin_in_prod(): void
    {
        $guard = new CorsConfigurationGuard('prod', 'https://app\.refleet\.example');

        $guard($this->createEvent());

        Assert::assertTrue(true);
    }

    #[Test]
    public function does_not_check_outside_prod(): void
    {
        $guard = new CorsConfigurationGuard('dev', '*');

        $guard($this->createEvent());

        Assert::assertTrue(true);
    }

    #[Test]
    public function skips_sub_requests(): void
    {
        $guard = new CorsConfigurationGuard('prod', '*');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, Request::create('/api/health'), HttpKernelInterface::SUB_REQUEST);

        $guard($event);

        Assert::assertTrue(true);
    }

    private function createEvent(): RequestEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, Request::create('/api/health'), HttpKernelInterface::MAIN_REQUEST);
    }
}
