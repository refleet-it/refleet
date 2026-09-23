<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Security;

use App\Shared\Domain\Exception\TooManyRequestsException;
use App\Shared\Infrastructure\Security\RateLimiter;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

#[CoversClass(RateLimiter::class)]
final class RateLimiterTest extends TestCase
{
    #[Test]
    public function allows_attempts_up_to_the_limit(): void
    {
        $rateLimiter = new RateLimiter(new ArrayAdapter());

        for ($i = 0; $i < 3; ++$i) {
            $rateLimiter->throttle('key', maxAttempts: 3, windowSeconds: 60);
        }

        Assert::assertTrue(true);
    }

    #[Test]
    public function throws_once_the_limit_is_exceeded(): void
    {
        $rateLimiter = new RateLimiter(new ArrayAdapter());

        for ($i = 0; $i < 3; ++$i) {
            $rateLimiter->throttle('key', maxAttempts: 3, windowSeconds: 60);
        }

        $this->expectException(TooManyRequestsException::class);
        $rateLimiter->throttle('key', maxAttempts: 3, windowSeconds: 60);
    }

    #[Test]
    public function tracks_different_keys_independently(): void
    {
        $rateLimiter = new RateLimiter(new ArrayAdapter());

        for ($i = 0; $i < 3; ++$i) {
            $rateLimiter->throttle('key-a', maxAttempts: 3, windowSeconds: 60);
        }

        $rateLimiter->throttle('key-b', maxAttempts: 3, windowSeconds: 60);

        Assert::assertTrue(true);
    }

    #[Test]
    public function key_for_ip_hashes_the_ip_with_a_prefix(): void
    {
        $key = RateLimiter::keyForIp('register', '203.0.113.1');

        Assert::assertSame('register_'.\hash('sha256', '203.0.113.1'), $key);
    }
}
