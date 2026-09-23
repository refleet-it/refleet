<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Application\Command\UpdatePlaybook;

use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookAppliesToEnum;
use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookKindEnum;
use App\Playbook\Playbook\Domain\Playbook\Exception\BuiltInPlaybookIsReadOnlyException;
use App\Playbook\Playbook\Domain\Playbook\Exception\PlaybookNotFoundException;
use App\Playbook\Playbook\Domain\Playbook\Repository\PlaybookRepositoryInterface;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\OrganizationId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookDefinition;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookParameter;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdatePlaybookHandler
{
    public function __construct(
        private PlaybookRepositoryInterface $playbooks,
    ) {
    }

    public function __invoke(UpdatePlaybookCommand $command): void
    {
        if (\str_starts_with($command->playbookId, PlaybookDefinition::BUILT_IN_ID_PREFIX)) {
            throw new BuiltInPlaybookIsReadOnlyException();
        }

        if (!Uuid::isValid($command->playbookId)) {
            throw new PlaybookNotFoundException();
        }

        $playbook = $this->playbooks->findByIdForOrganization(PlaybookId::fromString($command->playbookId), OrganizationId::fromString($command->organizationId));
        if (null === $playbook) {
            throw new PlaybookNotFoundException();
        }

        $playbook->update(new PlaybookDefinition(
            id: $command->playbookId,
            name: \trim($command->name),
            description: null === $command->description || '' === \trim($command->description) ? null : \trim($command->description),
            kind: PlaybookKindEnum::from($command->kind),
            appliesTo: PlaybookAppliesToEnum::from($command->appliesTo),
            body: $command->body,
            default: $command->default,
            parameters: \array_map(PlaybookParameter::fromArray(...), $command->parameters),
            engine: null === $command->engine ? null : CriteriaEngineEnum::from($command->engine),
            model: null === $command->model || '' === \trim($command->model) ? null : $command->model,
            builtIn: false,
        ));

        $this->playbooks->save($playbook);
    }
}
