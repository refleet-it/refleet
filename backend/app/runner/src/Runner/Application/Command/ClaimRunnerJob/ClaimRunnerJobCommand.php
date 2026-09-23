<?php

declare(strict_types=1);

namespace App\Runner\Runner\Application\Command\ClaimRunnerJob;

use App\Shared\Application\Command\Sync\CommandInterface;

final readonly class ClaimRunnerJobCommand implements CommandInterface
{
    /**
     * @param string[]|null $supportedKinds   e.g. ['qualification', 'change']; null claims any kind
     * @param string[]|null $supportedModes   e.g. ['ai']; null claims any mode
     * @param string[]|null $supportedEngines e.g. ['claude']; the static engine and/or AI-mode agents this runner can execute; null claims any engine
     */
    public function __construct(
        public string $runnerId,
        public string $organizationId,
        public ?array $supportedKinds = null,
        public ?array $supportedModes = null,
        public ?array $supportedEngines = null,
    ) {
    }
}
