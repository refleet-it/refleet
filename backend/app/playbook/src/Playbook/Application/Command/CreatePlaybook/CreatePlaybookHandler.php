<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Application\Command\CreatePlaybook;

use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookAppliesToEnum;
use App\Playbook\Playbook\Domain\Playbook\Enum\PlaybookKindEnum;
use App\Playbook\Playbook\Domain\Playbook\Model\Playbook;
use App\Playbook\Playbook\Domain\Playbook\Repository\PlaybookRepositoryInterface;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\AccountId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\OrganizationId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookDefinition;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookParameter;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreatePlaybookHandler
{
    public function __construct(
        private PlaybookRepositoryInterface $playbooks,
    ) {
    }

    public function __invoke(CreatePlaybookCommand $command): CreatedPlaybook
    {
        $id = PlaybookId::generate();

        $playbook = Playbook::create(
            $id,
            OrganizationId::fromString($command->organizationId),
            AccountId::fromString($command->createdByAccountId),
            new PlaybookDefinition(
                id: $id->asString(),
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
            ),
        );

        $this->playbooks->save($playbook);

        return new CreatedPlaybook($id->asString());
    }
}
