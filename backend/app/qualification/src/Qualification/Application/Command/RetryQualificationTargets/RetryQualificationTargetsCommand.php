<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Command\RetryQualificationTargets;

use App\Shared\Application\Command\Sync\CommandInterface;

/**
 * Sends failed targets back to the runner queue: one specific target when `targetId`
 * is given, otherwise every FAILED target of the qualification.
 */
final readonly class RetryQualificationTargetsCommand implements CommandInterface
{
    public function __construct(
        public string $qualificationId,
        public string $organizationId,
        public ?string $targetId = null,
    ) {
    }
}
