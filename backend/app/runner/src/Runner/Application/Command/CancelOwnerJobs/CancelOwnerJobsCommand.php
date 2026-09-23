<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\CancelOwnerJobs;

use App\Shared\Application\Command\Sync\CommandInterface;

/**
 * Dispatched by Qualification/Shift's own CancelQualification/CancelShift handlers to
 * cascade-cancel any PENDING/CLAIMED jobs still in flight for that owner, so a runner
 * never picks up work for something that was just cancelled.
 */
final readonly class CancelOwnerJobsCommand implements CommandInterface
{
    public function __construct(
        public string $ownerId,
        public string $organizationId,
    ) {
    }
}
