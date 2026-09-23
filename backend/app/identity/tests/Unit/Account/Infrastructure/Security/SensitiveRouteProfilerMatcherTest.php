<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Account\Infrastructure\Security;

use App\Identity\Account\Infrastructure\Security\SensitiveRouteProfilerMatcher;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * This matcher decides what the profiler is allowed to record, so its failure is silent by
 * construction: every request still succeeds, plaintext passwords just start being collected into
 * profiler storage where nobody looks for them. The cases below hold the refusal direction — for
 * each credential endpoint, and for the profiler switch itself.
 */
#[CoversClass(SensitiveRouteProfilerMatcher::class)]
final class SensitiveRouteProfilerMatcherTest extends TestCase
{
    #[Test]
    #[DataProvider('credentialPaths')]
    public function refuses_to_profile_a_request_carrying_credentials(string $path): void
    {
        // Arrange
        $matcher = new SensitiveRouteProfilerMatcher(profilerEnabled: true);

        // Assert
        Assert::assertFalse($matcher->matches(Request::create($path, 'POST')));
    }

    #[Test]
    public function profiles_an_ordinary_request_when_the_profiler_is_on(): void
    {
        // Arrange
        $matcher = new SensitiveRouteProfilerMatcher(profilerEnabled: true);

        // Assert
        Assert::assertTrue($matcher->matches(Request::create('/api/shifts', 'GET')));
    }

    // Without this the suite would pass just as happily if matches() always returned false, and the
    // profiler would be quietly dead in development.
    #[Test]
    public function profiles_nothing_at_all_while_the_profiler_is_off(): void
    {
        // Arrange
        $matcher = new SensitiveRouteProfilerMatcher(profilerEnabled: false);

        // Assert
        Assert::assertFalse($matcher->matches(Request::create('/api/shifts', 'GET')));
    }

    // The guard is a prefix test, so anything hanging off a credential endpoint is covered too.
    #[Test]
    public function refuses_to_profile_anything_nested_under_a_credential_endpoint(): void
    {
        // Arrange
        $matcher = new SensitiveRouteProfilerMatcher(profilerEnabled: true);

        // Assert
        Assert::assertFalse($matcher->matches(Request::create('/api/identity/login/refresh', 'POST')));
    }

    // ...and only a prefix: a sensitive path appearing further along does not disable profiling,
    // which is worth stating so nobody reads the guard as a substring search.
    #[Test]
    public function still_profiles_a_path_that_merely_mentions_a_credential_endpoint_later_on(): void
    {
        // Arrange
        $matcher = new SensitiveRouteProfilerMatcher(profilerEnabled: true);

        // Assert
        Assert::assertTrue($matcher->matches(Request::create('/api/projects/api/identity/login', 'GET')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function credentialPaths(): iterable
    {
        yield 'login' => ['/api/identity/login'];
        yield 'register' => ['/api/identity/register'];
        yield 'reset-password' => ['/api/identity/reset-password'];
        yield 'change-password' => ['/api/identity/change-password'];
    }
}
