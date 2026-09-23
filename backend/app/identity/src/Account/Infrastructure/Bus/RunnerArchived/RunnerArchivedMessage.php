<?php

declare(strict_types=1);

namespace App\Identity\Account\Infrastructure\Bus\RunnerArchived;

/**
 * Identity's own copy of the message the Runner context publishes when a runner is
 * archived. Deliberately duplicated rather than imported: the contexts share only the
 * wire format, so either can move to its own service without a code dependency.
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
