<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Domain\QualificationTarget\ValueObject;

use App\Shared\Domain\ValueObject\Id;

/**
 * Local copy of the Project context's ProjectId, per the bounded-context isolation rule
 * (Qualification never imports Project's Domain types directly).
 */
final readonly class ProjectId extends Id
{
}
