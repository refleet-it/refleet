<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\HeartbeatRunner;

final readonly class HeartbeatedRunner
{
    /**
     * @param string[]|null             $supportedEngines
     * @param string[]|null             $supportedModels
     * @param array<string, mixed>|null $usage
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $status,
        public string $lastSeenAt,
        public ?array $supportedEngines = null,
        public ?array $supportedModels = null,
        public ?array $usage = null,
        public ?string $version = null,
        public ?string $latestVersion = null,
        public bool $updateAvailable = false,
        /** One-shot: true only on the heartbeat that hands a dashboard update request over to the runner. */
        public bool $updateRequested = false,
    ) {
    }
}
