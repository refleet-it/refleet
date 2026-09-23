<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Bus\OwnerJobsCancellationRequested;

/**
 * Asks the Runner context to drop any jobs still in flight for an owner that was cancelled.
 *
 * Every context on this route keeps its own copy of this class; they agree on the
 * wire contract only, never on an import.
 */
final readonly class OwnerJobsCancellationRequestedMessage
{
    public function __construct(
        public string $ownerId,
        public string $organizationId,
    ) {
    }
}
