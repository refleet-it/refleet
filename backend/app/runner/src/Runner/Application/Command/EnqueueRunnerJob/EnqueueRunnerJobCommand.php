<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\EnqueueRunnerJob;

use App\Shared\Application\Command\Sync\CommandInterface;

/**
 * Dispatched by Qualification (kind=qualification) and Shift (kind=change) to spawn a
 * unit of runner work. `ownerId`/`ownerTargetId` are opaque to Runner — they are simply
 * round-tripped back on ReportRunnerJobResult so the caller's own RecordTargetResult
 * command can be dispatched. `ownerLabel` is a denormalized snapshot of the owning
 * Qualification/Shift's title, so Runner never needs to query back into another
 * context just to render a human-readable job list.
 */
final readonly class EnqueueRunnerJobCommand implements CommandInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        /** Chosen by the caller so it can record the id without waiting for a reply. */
        public string $jobId,
        public string $ownerId,
        public string $ownerTargetId,
        public string $ownerLabel,
        public string $organizationId,
        public string $kind,
        public string $mode,
        public array $payload,
        public ?string $engine = null,
    ) {
    }
}
