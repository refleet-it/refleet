<?php

declare(strict_types=1);

namespace App\Shift\Shift\Infrastructure\Api\DefineShiftChange;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'DefineShiftChange',
    description: 'Payload used to (re)define the change criteria applied to every target. Can be called repeatedly before the change is started.'
)]
final readonly class DefineShiftChangeRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['ai'])]
        #[OA\Property(type: 'string', enum: ['ai'], example: 'ai')]
        public string $changeMode = 'ai',
        #[Assert\Choice(choices: ['claude', 'kiro'])]
        #[OA\Property(description: 'Which agent CLI runs the prompt ("claude" or "kiro"), defaulting to "claude".', type: 'string', enum: ['claude', 'kiro'], example: 'claude')]
        public ?string $changeEngine = null,
        #[Assert\NotBlank]
        #[OA\Property(type: 'string', example: 'Bump acme/legacy-lib to ^3.0 in composer.json and run composer update acme/legacy-lib.')]
        public string $changePrompt = '',
        #[Assert\Length(max: 100)]
        #[OA\Property(description: 'Optional model id override. Not validated against a fixed list — see GET /runners/available-models for what the organization\'s runner fleet currently offers. Omit to let the runner use its own configured default.', type: 'string', example: 'claude-opus-5')]
        public ?string $changeModel = null,
        #[OA\Property(description: 'Organization-wide rules the agent follows before the change itself — composed from playbooks (POST /playbooks/compose) and freely edited. Sent to the agent verbatim as a "## Rules" section.', type: 'string', example: 'Commit subject: Conventional Commits, English, imperative, at most 72 characters.', nullable: true)]
        public ?string $changeRules = null,
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
        public ?array $changeSources = null,
    ) {
    }
}
