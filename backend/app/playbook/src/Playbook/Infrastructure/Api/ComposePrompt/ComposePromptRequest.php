<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Infrastructure\Api\ComposePrompt;

use App\Playbook\Playbook\Infrastructure\Api\PlaybookIdRequirement;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'ComposePrompt',
    description: 'Which playbooks to turn into text: any number of rules (in order) and at most one task with its parameter values.'
)]
final readonly class ComposePromptRequest
{
    /**
     * @param list<string>          $ruleIds
     * @param array<string, string> $parameters
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['change', 'qualification'])]
        #[OA\Property(description: 'What the prompt is for; every selected playbook must apply to it.', type: 'string', enum: ['change', 'qualification'], example: 'change')]
        public string $appliesTo,
        #[Assert\All([new Assert\NotBlank(), new Assert\Regex('/^'.PlaybookIdRequirement::PATTERN.'$/')])]
        #[Assert\Count(max: 50)]
        #[OA\Property(type: 'array', items: new OA\Items(type: 'string'), example: ['builtin:git-conventions'])]
        public array $ruleIds = [],
        #[Assert\Regex('/^'.PlaybookIdRequirement::PATTERN.'$/')]
        #[OA\Property(type: 'string', example: 'builtin:upgrade-dependency', nullable: true)]
        public ?string $taskId = null,
        #[Assert\All([new Assert\Type('string'), new Assert\Length(max: 2000)])]
        #[Assert\Count(max: 20)]
        #[OA\Property(description: "Values for the task's {{name}} placeholders, keyed by parameter name.", type: 'object', example: ['package' => 'acme/legacy-lib', 'version' => '^3.0'])]
        public array $parameters = [],
    ) {
    }
}
