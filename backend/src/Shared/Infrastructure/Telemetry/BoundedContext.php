<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Telemetry;

final class BoundedContext
{
    public const string SPAN_ATTRIBUTE = 'refleet.context';

    public static function fromClassName(string $className): ?string
    {
        if (1 !== \preg_match('/^App\\\\([A-Za-z]+)\\\\/', $className, $matches)) {
            return null;
        }

        return \strtolower($matches[1]);
    }
}
