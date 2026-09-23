<?php

declare(strict_types=1);

namespace App\Tests\Unit\Runner\Runner\Application\Query\GetRunner;

use App\Runner\Runner\Application\Query\GetRunner\GetRunnerHandler;
use App\Runner\Runner\Application\Query\GetRunner\GetRunnerQuery;
use App\Runner\Runner\Domain\Runner\Enum\RunnerStatusEnum;
use App\Runner\Runner\Domain\Runner\Exception\RunnerNotFoundException;
use App\Runner\Runner\Domain\Runner\Model\Runner;
use App\Runner\Runner\Domain\Runner\Repository\RunnerRepositoryInterface;
use App\Runner\Runner\Domain\Runner\Service\LatestRunnerVersionProviderInterface;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerId;
use App\Runner\Runner\Domain\RunnerJob\Repository\RunnerJobRepositoryInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetRunnerHandler::class)]
final class GetRunnerHandlerTest extends TestCase
{
    private RunnerRepositoryInterface&Stub $runners;

    private RunnerJobRepositoryInterface&Stub $runnerJobs;

    private GetRunnerHandler $handler;

    #[Test]
    public function returns_the_detail_of_an_idle_runner_with_no_claimed_job(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $runnerId = RunnerId::generate();
        $lastSeenAt = (new \DateTimeImmutable())->modify('-10 seconds');

        $runner = Runner::register(
            id: $runnerId,
            organizationId: $organizationId,
            name: 'runner-fleet-01',
            status: RunnerStatusEnum::IDLE,
            lastSeenAt: $lastSeenAt,
        );

        $this->runners->method('findByIdForOrganization')->willReturn($runner);
        $this->runnerJobs->method('findNamesWithActiveClaimedJob')->willReturn([]);

        // Act
        $result = ($this->handler)(new GetRunnerQuery(
            runnerId: $runnerId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertSame($runnerId->asString(), $result->id);
        Assert::assertSame('runner-fleet-01', $result->name);
        Assert::assertSame('idle', $result->status);
        Assert::assertSame($lastSeenAt->format('c'), $result->lastSeenAt);
    }

    #[Test]
    public function returns_the_detail_of_a_runner_currently_holding_a_claimed_job_as_working(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $runnerId = RunnerId::generate();

        $runner = Runner::register(
            id: $runnerId,
            organizationId: $organizationId,
            name: 'runner-fleet-01',
            status: RunnerStatusEnum::IDLE,
            lastSeenAt: (new \DateTimeImmutable())->modify('-10 seconds'),
        );

        $this->runners->method('findByIdForOrganization')->willReturn($runner);
        $this->runnerJobs->method('findNamesWithActiveClaimedJob')->willReturn(['runner-fleet-01']);

        // Act
        $result = ($this->handler)(new GetRunnerQuery(
            runnerId: $runnerId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertSame('working', $result->status);
    }

    #[Test]
    public function a_runner_that_stopped_heartbeating_is_reported_as_offline(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $runnerId = RunnerId::generate();

        $runner = Runner::register(
            id: $runnerId,
            organizationId: $organizationId,
            name: 'runner-fleet-01',
            status: RunnerStatusEnum::IDLE,
            lastSeenAt: (new \DateTimeImmutable())->modify('-1 hour'),
        );

        $this->runners->method('findByIdForOrganization')->willReturn($runner);
        $this->runnerJobs->method('findNamesWithActiveClaimedJob')->willReturn([]);

        // Act
        $result = ($this->handler)(new GetRunnerQuery(
            runnerId: $runnerId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertSame('offline', $result->status);
    }

    #[Test]
    public function throws_not_found_when_the_runner_does_not_exist_or_belongs_to_another_organization(): void
    {
        // Arrange: the repository scopes the lookup by organization at the query level, so
        // "doesn't exist" and "belongs to someone else" both surface as null here.
        $this->runners->method('findByIdForOrganization')->willReturn(null);

        // Assert
        $this->expectException(RunnerNotFoundException::class);

        // Act
        ($this->handler)(new GetRunnerQuery(
            runnerId: RunnerId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->runners = $this->createStub(RunnerRepositoryInterface::class);
        $this->runnerJobs = $this->createStub(RunnerJobRepositoryInterface::class);
        $this->handler = new GetRunnerHandler($this->runners, $this->runnerJobs, $this->createStub(LatestRunnerVersionProviderInterface::class));
    }
}
