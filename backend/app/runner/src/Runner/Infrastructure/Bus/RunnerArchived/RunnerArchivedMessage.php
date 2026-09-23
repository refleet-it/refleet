<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Bus\RunnerArchived;

/**
 * Published when a runner is archived, so Identity can retire the API key that runner
 * authenticated with. Identity owns a structurally identical copy of this class; the two
 * are bound only by the wire contract (see the transport's serializer), never by an import.
 */
final readonly class RunnerArchivedMessage
{
    public function __construct(
        public string $runnerId,
        public string $organizationId,
        public ?string $apiKeyId,
    ) {
    }
}
