<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\ShiftTarget\ValueObject;

use App\Shared\Domain\ValueObject\Id;

/**
 * Local copy of the Project context's ProjectId, per the bounded-context isolation rule
 * (Shift never imports Project's Domain types directly).
 */
final readonly class ProjectId extends Id
{
}
