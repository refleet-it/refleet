<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Application\Query\ComposePrompt;

use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookAppliesToEnum;
use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookKindEnum;
use App\Playbook\Playbook\Domain\Playbook\Exception\InvalidPlaybookException;
use App\Playbook\Playbook\Domain\Playbook\Exception\PlaybookNotFoundException;
use App\Playbook\Playbook\Domain\Playbook\Service\PlaybookDirectory;
use App\Playbook\Playbook\Domain\Playbook\Service\PromptComposer;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\ComposedPrompt;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\OrganizationId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookDefinition;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ComposePromptHandler
{
    public function __construct(
        private PlaybookDirectory $directory,
        private PromptComposer $composer,
    ) {
    }

    public function __invoke(ComposePromptQuery $query): ComposedPrompt
    {
        $organizationId = OrganizationId::fromString($query->organizationId);
        $usage = PlaybookAppliesToEnum::from($query->appliesTo);

        $rules = \array_map(fn (string $id): PlaybookDefinition => $this->resolve($id, $organizationId, $usage, PlaybookKindEnum::RULE), $query->ruleIds);
        $task = null === $query->taskId ? null : $this->resolve($query->taskId, $organizationId, $usage, PlaybookKindEnum::TASK);

        return $this->composer->compose(\array_values($rules), $task, $query->parameters);
    }

    private function resolve(string $id, OrganizationId $organizationId, PlaybookAppliesToEnum $usage, PlaybookKindEnum $expectedKind): PlaybookDefinition
    {
        $definition = $this->directory->find($id, $organizationId);
        if (null === $definition) {
            throw new PlaybookNotFoundException();
        }

        if ($definition->kind() !== $expectedKind) {
            throw new InvalidPlaybookException(\sprintf('Playbook "%s" is a %s, not a %s.', $definition->name(), $definition->kind()->value, $expectedKind->value));
        }

        if (!$definition->appliesTo()->covers($usage)) {
            throw new InvalidPlaybookException(\sprintf('Playbook "%s" does not apply to %s.', $definition->name(), $usage->value));
        }

        return $definition;
    }
}
