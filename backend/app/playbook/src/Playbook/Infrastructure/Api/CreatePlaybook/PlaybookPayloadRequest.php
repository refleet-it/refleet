<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Infrastructure\Api\CreatePlaybook;

use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookDefinition;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookParameter;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(
    title: 'PlaybookPayload',
    description: 'A playbook as the organization authors it. Rules cannot declare parameters, an engine or a model; only rules can be on by default.'
)]
final readonly class PlaybookPayloadRequest
{
    /**
     * @param list<array{name: string, label?: string|null, default?: string|null, required?: bool}> $parameters
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: PlaybookDefinition::NAME_MAX_LENGTH)]
        #[OA\Property(type: 'string', example: 'Upgrade a dependency')]
        public string $name,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['task', 'rule'])]
        #[OA\Property(type: 'string', enum: ['task', 'rule'], example: 'task')]
        public string $kind,
        #[Assert\NotBlank]
        #[Assert\Choice(choices: ['change', 'qualification', 'both'])]
        #[OA\Property(type: 'string', enum: ['change', 'qualification', 'both'], example: 'change')]
        public string $appliesTo,
        #[Assert\NotBlank]
        #[Assert\Length(max: PlaybookDefinition::BODY_MAX_LENGTH)]
        #[OA\Property(description: 'The prompt text. Tasks may use {{name}} placeholders for their declared parameters.', type: 'string', example: 'Upgrade the dependency `{{package}}` to `{{version}}`.')]
        public string $body,
        #[Assert\Length(max: 1000)]
        #[OA\Property(type: 'string', example: 'Bump one package to a target version and make the project pass with it.', nullable: true)]
        public ?string $description = null,
        #[OA\Property(description: 'Rules only: pre-selected whenever a new shift or qualification is defined.', type: 'boolean', example: false)]
        public bool $default = false,
        #[Assert\All([
            new Assert\Collection(
                fields: [
                    'name' => [new Assert\NotBlank(), new Assert\Regex(PlaybookParameter::NAME_PATTERN)],
                    'label' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 120)]),
                    'default' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 500)]),
                    'required' => new Assert\Optional(new Assert\Type('bool')),
                ],
            ),
        ])]
        #[Assert\Count(max: 20)]
        #[OA\Property(description: 'Tasks only: the {{name}} placeholders the body takes.', type: 'array', items: new OA\Items(type: 'object'))]
        public array $parameters = [],
        #[Assert\Choice(choices: ['claude', 'kiro'])]
        #[OA\Property(description: 'Tasks only: the agent to preselect.', type: 'string', enum: ['claude', 'kiro'], nullable: true)]
        public ?string $engine = null,
        #[Assert\Length(max: 100)]
        #[OA\Property(description: 'Tasks only: the model id to preselect.', type: 'string', example: 'claude-opus-5', nullable: true)]
        public ?string $model = null,
    ) {
    }
}
