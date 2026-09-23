<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\ClaimRunnerJob;

final readonly class ClaimedRunnerJob
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $jobId,
        public string $ownerId,
        public string $ownerTargetId,
        public string $ownerLabel,
        public string $kind,
        public string $mode,
        public array $payload,
        public string $leaseExpiresAt,
    ) {
    }
}
