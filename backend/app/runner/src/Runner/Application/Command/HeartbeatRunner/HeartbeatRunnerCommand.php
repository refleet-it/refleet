<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\HeartbeatRunner;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class HeartbeatRunnerCommand implements CommandInterface
{
    /**
     * @param string[]|null             $supportedEngines AI-mode engines this runner auto-detected on its host
     * @param string[]|null             $supportedModels  model ids this runner was configured to offer for its engine(s)
     * @param array<string, mixed>|null $usage            what the runner's last agent run reported about its consumption
     * @param string|null               $version          the runner package version the process is running
     */
    public function __construct(
        public string $organizationId,
        public string $name,
        /** Id of the API key the runner authenticated with, null when a browser session called this. */
        public ?string $apiKeyId = null,
        public ?array $supportedEngines = null,
        public ?array $supportedModels = null,
        public ?array $usage = null,
        public ?string $version = null,
    ) {
    }
}
