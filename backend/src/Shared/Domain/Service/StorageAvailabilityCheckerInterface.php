<?php

declare(strict_types=1);

namespace App\Shared\Domain\Service;

/**
 * Cheap, side-effect-free reachability probe for the active file storage backend.
 * Kept separate from FileStorageServiceInterface so callers that only need a
 * liveness signal (e.g. the /api/health endpoint) don't have to depend on the
 * full storage contract.
 */
interface StorageAvailabilityCheckerInterface
{
    public function isAvailable(): bool;
}
