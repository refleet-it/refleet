<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Application\Command\DeletePlaybook;

use App\Playbook\Playbook\Domain\Playbook\Exception\BuiltInPlaybookIsReadOnlyException;
use App\Playbook\Playbook\Domain\Playbook\Exception\PlaybookNotFoundException;
use App\Playbook\Playbook\Domain\Playbook\Repository\PlaybookRepositoryInterface;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\OrganizationId;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookDefinition;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\PlaybookId;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeletePlaybookHandler
{
    public function __construct(
        private PlaybookRepositoryInterface $playbooks,
    ) {
    }

    public function __invoke(DeletePlaybookCommand $command): void
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

        $this->playbooks->remove($playbook);
    }
}
