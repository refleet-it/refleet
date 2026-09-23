<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http\Routing;

final class Requirements
{
    /**
     * UUID format validation (v1-v7 compatible)
     * Matches format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx.
     */
    public const string UUID = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
}
