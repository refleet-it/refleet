<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Infrastructure\Api;

use App\Shared\Infrastructure\Http\Routing\Requirements;

/** Playbook ids are either a UUID or "builtin:<file>" — see PlaybookDirectory. */
final readonly class PlaybookIdRequirement
{
    public const string PATTERN = '(builtin:[a-z0-9-]+|'.Requirements::UUID.')';
}
