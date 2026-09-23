<?php

declare(strict_types=1);

namespace App\Runner\Runner\Domain\RunnerJob\ValueObject;

use App\Shared\Domain\ValueObject\Id;

/**
 * Opaque from Runner's point of view: either a QualificationId or a ShiftId,
 * disambiguated by RunnerJobKindEnum. Runner never dereferences it — it only ever
 * round-trips it back to its owning context via EnqueueRunnerJob/ReportRunnerJobResult.
 */
final readonly class RunnerJobOwnerId extends Id
{
}
