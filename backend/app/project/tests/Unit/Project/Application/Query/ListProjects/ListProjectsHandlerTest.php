<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Project\Application\Query\ListProjects;

use App\Project\Project\Application\Query\ListProjects\ListProjectsHandler;
use App\Project\Project\Application\Query\ListProjects\ListProjectsQuery;
use App\Project\Project\Domain\Project\Model\Project;
use App\Project\Project\Domain\Project\Repository\ProjectRepositoryInterface;
use App\Project\Project\Domain\Project\ValueObject\GitLabProjectId;
use App\Project\Project\Domain\Project\ValueObject\OrganizationId;
use App\Project\Project\Domain\Project\ValueObject\ProjectId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListProjectsHandler::class)]
final class ListProjectsHandlerTest extends TestCase
{
    private ProjectRepositoryInterface&Stub $projects;

    private ListProjectsHandler $handler;

    #[Test]
    public function lists_the_organization_fleet(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $project = Project::register(
            id: ProjectId::generate(),
            organizationId: $organizationId,
            externalId: GitLabProjectId::fromString('1'),
            name: 'Payments Service',
            path: 'team/payments-service',
            webUrl: null,
            defaultBranch: null,
            description: null,
        );
        $this->projects->method('findActiveByOrganizationId')->willReturn([$project]);

        // Act
        $result = ($this->handler)(new ListProjectsQuery($organizationId->asString()));

        // Assert
        Assert::assertCount(1, $result);
        Assert::assertSame('Payments Service', $result[0]->name);
        Assert::assertSame('1', $result[0]->externalId);
    }

    #[Test]
    public function returns_an_empty_array_when_the_organization_has_no_projects(): void
    {
        // Arrange
        $this->projects->method('findActiveByOrganizationId')->willReturn([]);

        // Act
        $result = ($this->handler)(new ListProjectsQuery(OrganizationId::generate()->asString()));

        // Assert
        Assert::assertSame([], $result);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->projects = $this->createStub(ProjectRepositoryInterface::class);
        $this->handler = new ListProjectsHandler($this->projects);
    }
}
