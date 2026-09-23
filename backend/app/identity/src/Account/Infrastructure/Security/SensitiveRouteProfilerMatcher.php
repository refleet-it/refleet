<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

/**
 * Restricts the Symfony Profiler to environments where PROFILER_ENABLED=true,
 * and prevents it from collecting data for sensitive credential endpoints.
 */
final readonly class SensitiveRouteProfilerMatcher implements RequestMatcherInterface
{
    private const array SENSITIVE_PATHS = [
        '/api/identity/login',
        '/api/identity/register',
        '/api/identity/reset-password',
        '/api/identity/change-password',
        '/api/identity/cli-authorizations',
    ];

    public function __construct(private bool $profilerEnabled)
    {
    }

    #[\Override]
    public function matches(Request $request): bool
    {
        if (!$this->profilerEnabled) {
            return false;
        }

        return \array_all(self::SENSITIVE_PATHS, static fn (string $path) => !\str_starts_with($request->getPathInfo(), $path));
    }
}
