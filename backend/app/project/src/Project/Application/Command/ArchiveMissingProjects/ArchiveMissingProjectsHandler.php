<?php

declare(strict_types=1);

namespace App\Project\Project\Application\Command\ArchiveMissingProjects;

use App\Project\Project\Domain\Project\Repository\ProjectRepositoryInterface;
use App\Project\Project\Domain\Project\ValueObject\OrganizationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ArchiveMissingProjectsHandler
{
    public function __construct(
        private ProjectRepositoryInterface $projects,
    ) {
    }

    public function __invoke(ArchiveMissingProjectsCommand $command): ArchivedMissingProjects
    {
        $organizationId = OrganizationId::fromString($command->organizationId);
        $seenExternalIds = \array_flip($command->seenExternalIds);

        $archivedCount = 0;

        foreach ($this->projects->findActiveByOrganizationId($organizationId) as $project) {
            if (isset($seenExternalIds[$project->externalId()->asString()])) {
                continue;
            }

            $project->archive();
            $this->projects->save($project);
            ++$archivedCount;
        }

        return new ArchivedMissingProjects(archivedCount: $archivedCount);
    }
}
