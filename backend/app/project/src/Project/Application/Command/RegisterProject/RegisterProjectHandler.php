<?php

declare(strict_types=1);

namespace App\Project\Project\Application\Command\RegisterProject;

use App\Project\Project\Domain\Project\Model\Project;
use App\Project\Project\Domain\Project\Repository\ProjectRepositoryInterface;
use App\Project\Project\Domain\Project\ValueObject\GitLabProjectId;
use App\Project\Project\Domain\Project\ValueObject\OrganizationId;
use App\Project\Project\Domain\Project\ValueObject\ProjectId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RegisterProjectHandler
{
    public function __construct(
        private ProjectRepositoryInterface $projects,
    ) {
    }

    public function __invoke(RegisterProjectCommand $command): RegisteredProject
    {
        $organizationId = OrganizationId::fromString($command->organizationId);
        $externalId = GitLabProjectId::fromString($command->externalId);

        $project = $this->projects->findByExternalId($organizationId, $externalId);
        $wasCreated = null === $project;

        if (null === $project) {
            $project = Project::register(
                id: ProjectId::generate(),
                organizationId: $organizationId,
                externalId: $externalId,
                name: $command->name,
                path: $command->path,
                webUrl: $command->webUrl,
                defaultBranch: $command->defaultBranch,
                description: $command->description,
            );
        } else {
            $project->update(
                name: $command->name,
                path: $command->path,
                webUrl: $command->webUrl,
                defaultBranch: $command->defaultBranch,
                description: $command->description,
            );
        }

        $this->projects->save($project);

        return new RegisteredProject(
            id: $project->id()->asString(),
            name: $project->name(),
            externalId: $project->externalId()->asString(),
            path: $project->path(),
            webUrl: $project->webUrl(),
            defaultBranch: $project->defaultBranch(),
            description: $project->description(),
            createdAt: $project->createdAt()->format('c'),
            lastSyncedAt: $project->lastSyncedAt()?->format('c'),
            wasCreated: $wasCreated,
        );
    }
}
