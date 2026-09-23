<?php

declare(strict_types=1);

namespace App\Project\Project\Infrastructure\Service;

use App\Project\Project\Application\Command\ArchiveMissingProjects\ArchivedMissingProjects;
use App\Project\Project\Application\Command\ArchiveMissingProjects\ArchiveMissingProjectsCommand;
use App\Project\Project\Application\Command\RegisterProject\RegisterProjectCommand;
use App\Shared\Domain\Service\ProjectRegistryInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class ProjectRegistry implements ProjectRegistryInterface
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    #[\Override]
    public function register(
        string $organizationId,
        string $externalId,
        string $name,
        string $path,
        ?string $webUrl = null,
        ?string $defaultBranch = null,
        ?string $description = null,
    ): void {
        $this->bus->dispatch(new RegisterProjectCommand(
            organizationId: $organizationId,
            externalId: $externalId,
            name: $name,
            path: $path,
            webUrl: $webUrl,
            defaultBranch: $defaultBranch,
            description: $description,
        ));
    }

    #[\Override]
    public function archiveMissing(string $organizationId, array $seenExternalIds): int
    {
        $handledStamp = $this->bus->dispatch(new ArchiveMissingProjectsCommand(
            organizationId: $organizationId,
            seenExternalIds: $seenExternalIds,
        ))->last(HandledStamp::class);

        /** @var ArchivedMissingProjects $result */
        $result = $handledStamp?->getResult();

        return $result->archivedCount;
    }
}
