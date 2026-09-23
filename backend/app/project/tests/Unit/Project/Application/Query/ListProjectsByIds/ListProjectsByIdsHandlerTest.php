<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Project\Application\Query\ListProjectsByIds;

use App\Project\Project\Application\Query\ListProjectsByIds\ListProjectsByIdsHandler;
use App\Project\Project\Application\Query\ListProjectsByIds\ListProjectsByIdsQuery;
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

#[CoversClass(ListProjectsByIdsHandler::class)]
final class ListProjectsByIdsHandlerTest extends TestCase
{
    private ProjectRepositoryInterface&MockObject $projects;

    private ListProjectsByIdsHandler $handler;

    #[Test]
    public function lists_only_the_requested_projects(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $projectId = ProjectId::generate();
        $project = Project::register(
            id: $projectId,
            organizationId: $organizationId,
            externalId: GitLabProjectId::fromString('48210942'),
            name: 'Payments Service',
            path: 'team/payments-service',
            webUrl: null,
            defaultBranch: null,
            description: null,
        );

        $this->projects
            ->expects($this->once())
            ->method('findByIds')
            ->with(
                $this->callback(static fn (OrganizationId $id): bool => $id->equals($organizationId)),
                $this->callback(function (array $ids) use ($projectId): bool {
                    $this->assertCount(1, $ids);
                    $this->assertTrue($ids[0]->equals($projectId));

                    return true;
                }),
            )
            ->willReturn([$project]);

        // Act
        $result = ($this->handler)(new ListProjectsByIdsQuery($organizationId->asString(), [$projectId->asString()]));

        // Assert
        Assert::assertCount(1, $result);
        Assert::assertSame('Payments Service', $result[0]->name);
        Assert::assertSame($projectId->asString(), $result[0]->id);
    }

    #[Test]
    public function returns_an_empty_array_without_querying_the_repository_when_no_ids_are_given(): void
    {
        // Arrange
        $this->projects->expects($this->never())->method('findByIds');

        // Act
        $result = ($this->handler)(new ListProjectsByIdsQuery(OrganizationId::generate()->asString(), []));

        // Assert
        Assert::assertSame([], $result);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->projects = $this->createMock(ProjectRepositoryInterface::class);
        $this->handler = new ListProjectsByIdsHandler($this->projects);
    }
}
