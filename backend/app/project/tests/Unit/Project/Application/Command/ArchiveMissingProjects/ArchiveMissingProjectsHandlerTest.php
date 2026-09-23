<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Project\Application\Command\ArchiveMissingProjects;

use App\Project\Project\Application\Command\ArchiveMissingProjects\ArchiveMissingProjectsCommand;
use App\Project\Project\Application\Command\ArchiveMissingProjects\ArchiveMissingProjectsHandler;
use App\Project\Project\Domain\Project\Model\Project;
use App\Project\Project\Domain\Project\Repository\ProjectRepositoryInterface;
use App\Project\Project\Domain\Project\ValueObject\GitLabProjectId;
use App\Project\Project\Domain\Project\ValueObject\OrganizationId;
use App\Project\Project\Domain\Project\ValueObject\ProjectId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArchiveMissingProjectsHandler::class)]
final class ArchiveMissingProjectsHandlerTest extends TestCase
{
    private ProjectRepositoryInterface&MockObject $projects;

    private ArchiveMissingProjectsHandler $handler;

    #[Test]
    public function archives_active_projects_not_seen_in_the_latest_sync(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $stillInGitLab = $this->registerProject($organizationId, '1', 'Payments Service');
        $removedFromGitLab = $this->registerProject($organizationId, '2', 'Billing Service');

        $this->projects->method('findActiveByOrganizationId')->willReturn([$stillInGitLab, $removedFromGitLab]);

        $saved = [];
        $this->projects
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Project $project) use (&$saved): bool {
                $saved[] = $project;

                return true;
            }));

        // Act
        $result = ($this->handler)(new ArchiveMissingProjectsCommand(
            organizationId: $organizationId->asString(),
            seenExternalIds: ['1'],
        ));

        // Assert
        Assert::assertSame(1, $result->archivedCount);
        Assert::assertCount(1, $saved);
        Assert::assertTrue($saved[0]->id()->equals($removedFromGitLab->id()));
        Assert::assertTrue($removedFromGitLab->isArchived());
        Assert::assertFalse($stillInGitLab->isArchived());
    }

    #[Test]
    public function archives_nothing_when_every_active_project_was_seen(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $project = $this->registerProject($organizationId, '1', 'Payments Service');

        $this->projects->method('findActiveByOrganizationId')->willReturn([$project]);
        $this->projects->expects($this->never())->method('save');

        // Act
        $result = ($this->handler)(new ArchiveMissingProjectsCommand(
            organizationId: $organizationId->asString(),
            seenExternalIds: ['1'],
        ));

        // Assert
        Assert::assertSame(0, $result->archivedCount);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->projects = $this->createMock(ProjectRepositoryInterface::class);
        $this->handler = new ArchiveMissingProjectsHandler($this->projects);
    }

    private function registerProject(OrganizationId $organizationId, string $externalId, string $name): Project
    {
        return Project::register(
            id: ProjectId::generate(),
            organizationId: $organizationId,
            externalId: GitLabProjectId::fromString($externalId),
            name: $name,
            path: 'team/'.$externalId,
            webUrl: null,
            defaultBranch: null,
            description: null,
        );
    }
}
