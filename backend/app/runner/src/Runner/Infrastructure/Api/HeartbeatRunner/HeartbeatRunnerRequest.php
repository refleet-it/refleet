<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Api\HeartbeatRunner;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'HeartbeatRunner',
    description: 'Payload used by a runner to register itself (on first call) and report that it is still alive.'
)]
final readonly class HeartbeatRunnerRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[OA\Property(type: 'string', example: 'runner-fleet-01')]
        public string $name,
        /**
         * @var string[]|null
         */
        #[Assert\All([new Assert\Choice(choices: ['claude', 'kiro'])])]
        #[OA\Property(description: 'AI-mode engines this runner auto-detected on its host. Omit if unknown/not applicable.', type: 'array', items: new OA\Items(type: 'string', enum: ['claude', 'kiro']))]
        public ?array $supportedEngines = null,
        /**
         * @var string[]|null
         */
        #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 100)])]
        #[OA\Property(description: 'Model ids this runner was configured to offer for its engine(s) — operator-declared, not auto-detected (neither the claude CLI nor kiro-cli expose a way to list this at runtime). Omit if unknown/not applicable.', type: 'array', items: new OA\Items(type: 'string'))]
        public ?array $supportedModels = null,
        #[Assert\Valid]
        #[OA\Property(description: 'What the last agent run on this runner reported about its consumption. Omit until a job has run; a heartbeat without it keeps the previously reported figures.')]
        public ?RunnerUsageRequest $usage = null,
        #[Assert\Length(max: 64)]
        #[OA\Property(description: 'The @refleet-it/runner package version this process is running, e.g. 0.1.186. Omit if unknown.', type: 'string', example: '0.1.186', nullable: true)]
        public ?string $version = null,
    ) {
    }
}
