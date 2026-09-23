<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\Shift\ValueObject;

use App\Shared\Domain\ValueObject\Id;

/**
 * Local, opaque copy of the Qualification context's QualificationId, per the
 * bounded-context isolation rule (Shift never imports Qualification's Domain types
 * directly). Records which Qualification, if any, this Shift was created from.
 */
final readonly class QualificationId extends Id
{
}
