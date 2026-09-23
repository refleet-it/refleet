<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Bus\RunnerJobRequested;

/**
 * Asks the Runner context to queue a job. The job id is chosen by the sender so it can record it on its own target without waiting for a reply.
 *
 * Every context on this route keeps its own copy of this class; they agree on the
 * wire contract only, never on an import.
 */
final readonly class RunnerJobRequestedMessage
{
    public function __construct(
        public string $jobId,
        public string $ownerId,
        public string $ownerTargetId,
        public string $ownerLabel,
        public string $organizationId,
        public string $kind,
        public string $mode,
        /** @var array<string, mixed> */
        public array $payload,
        public ?string $engine,
    ) {
    }
}
