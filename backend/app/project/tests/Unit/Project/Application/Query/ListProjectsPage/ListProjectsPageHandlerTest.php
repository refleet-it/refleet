<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Project\Application\Query\ListProjectsPage;

use App\Project\Project\Application\Query\ListProjects\ProjectOverview;
use App\Project\Project\Application\Query\ListProjectsPage\ListProjectsPageHandler;
use App\Project\Project\Application\Query\ListProjectsPage\ListProjectsPageQuery;
use App\Project\Project\Domain\Project\Model\Project;
use App\Project\Project\Domain\Project\Repository\ProjectRepositoryInterface;
use App\Project\Project\Domain\Project\ValueObject\GitLabProjectId;
use App\Project\Project\Domain\Project\ValueObject\OrganizationId;
use App\Project\Project\Domain\Project\ValueObject\ProjectId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\Sorting\SortParameters;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListProjectsPageHandler::class)]
final class ListProjectsPageHandlerTest extends TestCase
{
    private ProjectRepositoryInterface&MockObject $projects;

    private ListProjectsPageHandler $handler;

    #[Test]
    public function returns_a_page_of_projects_mapped_to_overviews(): void
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

        $this->projects
            ->expects($this->once())
            ->method('getPaginatedList')
            ->willReturnCallback(static function (OrganizationId $givenOrganizationId, PaginationParameters $pagination, ?SortParameters $sorting) use ($organizationId, $project): ListResponse {
                Assert::assertTrue($organizationId->equals($givenOrganizationId));
                Assert::assertSame(50, $pagination->getLimit());

                return ListResponse::create(
                    items: [$project],
                    totalItems: 51,
                    pagination: $pagination,
                );
            });

        $this->handler = new ListProjectsPageHandler($this->projects);

        // Act
        $result = ($this->handler)(new ListProjectsPageQuery(
            organizationId: $organizationId->asString(),
            limit: 50,
        ));

        // Assert
        Assert::assertCount(1, $result->getItems());
        Assert::assertInstanceOf(ProjectOverview::class, $result->getItems()[0]);
        Assert::assertSame('Payments Service', $result->getItems()[0]->name);
        Assert::assertTrue($result->hasNextPage());
        Assert::assertSame(51, $result->getTotalItems());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function returns_an_empty_page_when_the_organization_has_no_projects(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $this->projects
            ->method('getPaginatedList')
            ->willReturn(ListResponse::create(
                items: [],
                totalItems: 0,
                pagination: PaginationParameters::fromRequest(),
            ));

        $this->handler = new ListProjectsPageHandler($this->projects);

        // Act
        $result = ($this->handler)(new ListProjectsPageQuery(organizationId: $organizationId->asString()));

        // Assert
        Assert::assertSame([], $result->getItems());
        Assert::assertFalse($result->hasNextPage());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->projects = $this->createMock(ProjectRepositoryInterface::class);
    }
}
