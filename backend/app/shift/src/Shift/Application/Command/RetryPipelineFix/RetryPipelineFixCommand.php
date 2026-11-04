<?php

declare(strict_types=1);

namespace App\Shift\Shift\Application\Command\RetryPipelineFix;

use App\Shared\Application\Command\Sync\CommandInterface;

/**
 * Gives a target whose pipeline fix gave up a fresh set of attempts. Unlike re-running the
 * change, this leaves the merge request's branch and everything already on it alone.
 */
final readonly class RetryPipelineFixCommand implements CommandInterface
{
    public function __construct(
        public string $shiftId,
        public string $shiftTargetId,
        public string $organizationId,
    ) {
    }
}
