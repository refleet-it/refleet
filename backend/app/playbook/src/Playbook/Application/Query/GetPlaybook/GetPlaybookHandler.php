<?php

declare(strict_types=1);

namespace App\Playbook\Playbook\Application\Query\GetPlaybook;

use App\Playbook\Playbook\Application\Query\ListPlaybooks\PlaybookView;
use App\Playbook\Playbook\Domain\Playbook\Exception\PlaybookNotFoundException;
use App\Playbook\Playbook\Domain\Playbook\Service\PlaybookDirectory;
use App\Playbook\Playbook\Domain\Playbook\ValueObject\OrganizationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetPlaybookHandler
{
    public function __construct(
        private PlaybookDirectory $directory,
    ) {
    }

    public function __invoke(GetPlaybookQuery $query): PlaybookView
    {
        $definition = $this->directory->find($query->playbookId, OrganizationId::fromString($query->organizationId));
        if (null === $definition) {
            throw new PlaybookNotFoundException();
        }

        return PlaybookView::fromDefinition($definition);
    }
}
