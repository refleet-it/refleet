<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Api\ClaimRunnerJob;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'ClaimRunnerJob',
    description: 'Payload used by a runner to claim the next available job for its organization (resolved from the API key used to authenticate).'
)]
final readonly class ClaimRunnerJobRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[OA\Property(type: 'string', example: 'runner-fleet-01')]
        public string $runnerId,
        /**
         * @var string[]|null
         */
        #[Assert\All([new Assert\Choice(choices: ['qualification', 'change'])])]
        #[OA\Property(description: 'Job kinds this runner can execute. Omit to accept both.', type: 'array', items: new OA\Items(type: 'string', enum: ['qualification', 'change']))]
        public ?array $supportedKinds = null,
        /**
         * @var string[]|null
         */
        #[Assert\All([new Assert\Choice(choices: ['ai'])])]
        #[OA\Property(description: 'Job modes this runner can execute. Omit to accept any.', type: 'array', items: new OA\Items(type: 'string', enum: ['ai']))]
        public ?array $supportedModes = null,
        /**
         * @var string[]|null
         */
        #[Assert\All([new Assert\Choice(choices: ['claude', 'kiro'])])]
        #[OA\Property(description: 'Agents this runner auto-detected on its host and can run prompts through. Omit to accept any engine.', type: 'array', items: new OA\Items(type: 'string', enum: ['claude', 'kiro']))]
        public ?array $supportedEngines = null,
    ) {
    }
}
