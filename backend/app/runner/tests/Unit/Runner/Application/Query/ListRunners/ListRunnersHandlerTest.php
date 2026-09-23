<?php

declare(strict_types=1);

namespace App\Tests\Unit\Runner\Runner\Application\Query\ListRunners;

use App\Runner\Runner\Application\Query\ListRunners\ListRunnersHandler;
use App\Runner\Runner\Application\Query\ListRunners\ListRunnersQuery;
use App\Runner\Runner\Domain\Runner\Enum\RunnerStatusEnum;
use App\Runner\Runner\Domain\Runner\Model\Runner;
use App\Runner\Runner\Domain\Runner\Repository\RunnerRepositoryInterface;
use App\Runner\Runner\Domain\Runner\Service\LatestRunnerVersionProviderInterface;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerId;
use App\Runner\Runner\Domain\RunnerJob\Repository\RunnerJobRepositoryInterface;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListRunnersHandler::class)]
final class ListRunnersHandlerTest extends TestCase
{
    private RunnerRepositoryInterface&MockObject $runners;

    private RunnerJobRepositoryInterface&MockObject $runnerJobs;

    private ListRunnersHandler $handler;

    #[Test]
    public function maps_an_idle_runner_with_no_claimed_job_to_an_overview(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $lastSeenAt = (new \DateTimeImmutable())->modify('-10 seconds');

        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-01',
            status: RunnerStatusEnum::IDLE,
            lastSeenAt: $lastSeenAt,
        );

        $this->runners
            ->expects($this->once())
            ->method('getPaginatedList')
            ->willReturn(ListResponse::create(
                items: [$runner],
                totalItems: 1,
                pagination: PaginationParameters::fromRequest(),
            ));

        $this->runnerJobs
            ->expects($this->once())
            ->method('findNamesWithActiveClaimedJob')
            ->with($organizationId, ['runner-fleet-01'])
            ->willReturn([]);

        // Act
        $result = ($this->handler)(new ListRunnersQuery(organizationId: $organizationId->asString()));

        // Assert
        Assert::assertCount(1, $result->getItems());
        Assert::assertSame($runner->id()->asString(), $result->getItems()[0]->id);
        Assert::assertSame('runner-fleet-01', $result->getItems()[0]->name);
        Assert::assertSame('idle', $result->getItems()[0]->status);
        Assert::assertSame($lastSeenAt->format('c'), $result->getItems()[0]->lastSeenAt);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function maps_a_runner_holding_a_claimed_job_to_working(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-01',
            status: RunnerStatusEnum::IDLE,
            lastSeenAt: (new \DateTimeImmutable())->modify('-10 seconds'),
        );

        $this->runners
            ->method('getPaginatedList')
            ->willReturn(ListResponse::create(
                items: [$runner],
                totalItems: 1,
                pagination: PaginationParameters::fromRequest(),
            ));

        $this->runnerJobs
            ->method('findNamesWithActiveClaimedJob')
            ->willReturn(['runner-fleet-01']);

        // Act
        $result = ($this->handler)(new ListRunnersQuery(organizationId: $organizationId->asString()));

        // Assert
        Assert::assertSame('working', $result->getItems()[0]->status);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function a_runner_that_stopped_heartbeating_is_reported_as_offline(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $runner = Runner::register(
            id: RunnerId::generate(),
            organizationId: $organizationId,
            name: 'runner-fleet-01',
            status: RunnerStatusEnum::IDLE,
            lastSeenAt: (new \DateTimeImmutable())->modify('-1 hour'),
        );

        $this->runners
            ->method('getPaginatedList')
            ->willReturn(ListResponse::create(
                items: [$runner],
                totalItems: 1,
                pagination: PaginationParameters::fromRequest(),
            ));

        $this->runnerJobs
            ->method('findNamesWithActiveClaimedJob')
            ->willReturn([]);

        // Act
        $result = ($this->handler)(new ListRunnersQuery(organizationId: $organizationId->asString()));

        // Assert
        Assert::assertSame('offline', $result->getItems()[0]->status);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function returns_an_empty_page_when_the_organization_has_no_runners(): void
    {
        // Arrange
        $this->runners
            ->method('getPaginatedList')
            ->willReturn(ListResponse::create(
                items: [],
                totalItems: 0,
                pagination: PaginationParameters::fromRequest(),
            ));

        $this->runnerJobs
            ->method('findNamesWithActiveClaimedJob')
            ->willReturn([]);

        // Act
        $result = ($this->handler)(new ListRunnersQuery(organizationId: OrganizationId::generate()->asString()));

        // Assert
        Assert::assertSame([], $result->getItems());
        Assert::assertFalse($result->hasNextPage());
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->runners = $this->createMock(RunnerRepositoryInterface::class);
        $this->runnerJobs = $this->createMock(RunnerJobRepositoryInterface::class);
        $this->handler = new ListRunnersHandler($this->runners, $this->runnerJobs, $this->createStub(LatestRunnerVersionProviderInterface::class));
    }
}
