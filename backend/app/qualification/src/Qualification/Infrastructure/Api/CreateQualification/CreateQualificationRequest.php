<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Infrastructure\Api\CreateQualification;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'CreateQualification',
    description: 'Payload used to draft a new qualification and resolve the projects it evaluates. Criteria is an AI prompt an agent scores every project against.'
)]
final readonly class CreateQualificationRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        #[OA\Property(type: 'string', example: 'Find projects depending on acme/legacy-lib')]
        public string $title,
        #[OA\Property(type: 'string', example: 'Checks every repository for the deprecated acme/legacy-lib dependency.')]
        public ?string $description = null,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['ai'])]
        #[OA\Property(type: 'string', enum: ['ai'], example: 'ai')]
        public string $qualificationMode = 'ai',
        #[Assert\Choice(choices: ['claude', 'kiro'])]
        #[OA\Property(description: 'Which agent CLI runs the prompt ("claude" or "kiro"), defaulting to "claude".', type: 'string', enum: ['claude', 'kiro'], example: 'claude')]
        public ?string $qualificationEngine = null,
        #[Assert\NotBlank]
        #[OA\Property(type: 'string', example: 'Does this repository depend on acme/legacy-lib?')]
        public string $qualificationPrompt = '',
        #[Assert\Length(max: 100)]
        #[OA\Property(description: 'Optional model id override. Not validated against a fixed list — see GET /runners/available-models for what the organization\'s runner fleet currently offers. Omit to let the runner use its own configured default.', type: 'string', example: 'claude-sonnet-5')]
        public ?string $qualificationModel = null,
        /**
         * @var string[]|null
         */
        #[Assert\All([new Assert\Uuid()])]
        #[OA\Property(description: 'Explicit project IDs to evaluate. Omit or leave empty to evaluate every project in the organization.', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'))]
        public ?array $projectIds = null,
        #[OA\Property(description: 'Organization-wide rules the agent follows while qualifying — composed from playbooks (POST /playbooks/compose) and freely edited. Sent to the agent verbatim as a "## Rules" section.', type: 'string', example: 'Base the score only on files you actually inspected; cite their paths.', nullable: true)]
        public ?string $qualificationRules = null,
        /**
         * @var list<array{id: string, name: string, kind: string, builtIn?: bool}>|null
         */
        #[Assert\All([
            new Assert\Collection(
                fields: [
                    'id' => [new Assert\NotBlank(), new Assert\Length(max: 120)],
                    'name' => [new Assert\NotBlank(), new Assert\Length(max: 120)],
                    'kind' => new Assert\Choice(choices: ['task', 'rule']),
                    'builtIn' => new Assert\Optional(new Assert\Type('bool')),
                ],
            ),
        ])]
        #[Assert\Count(max: 50)]
        #[OA\Property(description: 'Which playbooks the prompt and rules were composed from, for display only — the text above is what actually runs.', type: 'array', items: new OA\Items(type: 'object'))]
        public ?array $qualificationSources = null,
    ) {
    }
}
