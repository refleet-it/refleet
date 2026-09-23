<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Query\ListRunners;

final readonly class RunnerOverview
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
        public ?string $lastSeenAt,
        public string $createdAt,
        public ?string $archivedAt = null,
        public ?array $supportedEngines = null,
        public ?array $supportedModels = null,
        public ?array $usage = null,
        public ?string $version = null,
        public ?string $latestVersion = null,
        public bool $updateAvailable = false,
        public ?string $updateRequestedAt = null,
    ) {
    }
}
