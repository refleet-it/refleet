<?php

declare(strict_types=1);

namespace App\Runner\Runner\Domain\RunnerJob\ValueObject;

use App\Shared\Domain\ValueObject\Id;

/**
 * Opaque from Runner's point of view: either a QualificationTargetId or a
 * ShiftTargetId, disambiguated by RunnerJobKindEnum.
 */
final readonly class RunnerJobOwnerTargetId extends Id
{
}
