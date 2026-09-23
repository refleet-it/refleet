<?php

declare(strict_types=1);

namespace App\Tests\Unit\Runner\Runner\Application\Query\ListRunnerJobs;

use App\Runner\Runner\Application\Query\ListRunnerJobs\ListRunnerJobsHandler;
use App\Runner\Runner\Application\Query\ListRunnerJobs\ListRunnerJobsQuery;
use App\Runner\Runner\Domain\Runner\Enum\RunnerStatusEnum;
use App\Runner\Runner\Domain\Runner\Exception\RunnerNotFoundException;
use App\Runner\Runner\Domain\Runner\Model\Runner;
use App\Runner\Runner\Domain\Runner\Repository\RunnerRepositoryInterface;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\Runner\ValueObject\RunnerId;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobKindEnum;
use App\Runner\Runner\Domain\RunnerJob\Model\RunnerJob;
use App\Runner\Runner\Domain\RunnerJob\Repository\RunnerJobRepositoryInterface;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerTargetId;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListRunnerJobsHandler::class)]
final class ListRunnerJobsHandlerTest extends TestCase
{
    private RunnerRepositoryInterface&Stub $runners;

    private RunnerJobRepositoryInterface&MockObject $runnerJobs;

    private ListRunnerJobsHandler $handler;

    #[Test]
    public function maps_the_runners_claimed_jobs_to_overviews_using_only_the_jobs_own_data(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $runnerId = RunnerId::generate();
        $ownerId = RunnerJobOwnerId::generate();
        $ownerTargetId = RunnerJobOwnerTargetId::generate();

        $runner = Runner::register(
            id: $runnerId,
            organizationId: $organizationId,
            name: 'runner-fleet-01',
            status: RunnerStatusEnum::IDLE,
        );
        $this->runners->method('findByIdForOrganization')->willReturn($runner);

        $job = $this->createJob($ownerId, $ownerTargetId, $organizationId, RunnerJobKindEnum::QUALIFICATION, [
            'mode' => 'ai',
            'targetFile' => 'composer.json',
            'pattern' => '"acme/legacy-lib"',
            'project' => ['name' => 'Payments Service', 'path' => 'backend-team/payments-service'],
        ]);
        $job->claim('runner-fleet-01', 300);

        $this->runnerJobs
            ->expects($this->once())
            ->method('getPaginatedListByClaimedBy')
            ->with($organizationId, 'runner-fleet-01', $this->anything())
            ->willReturn(ListResponse::create([$job], 1, PaginationParameters::fromRequest()));

        // Act
        $result = ($this->handler)(new ListRunnerJobsQuery(
            runnerId: $runnerId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertCount(1, $result->getItems());
        Assert::assertSame($job->id()->asString(), $result->getItems()[0]->id);
        Assert::assertSame('qualification', $result->getItems()[0]->kind);
        Assert::assertSame('ai', $result->getItems()[0]->mode);
        Assert::assertSame('claimed', $result->getItems()[0]->status);
        Assert::assertSame($ownerId->asString(), $result->getItems()[0]->ownerId);
        Assert::assertSame('Bump acme/legacy-lib', $result->getItems()[0]->ownerLabel);
        Assert::assertSame($ownerTargetId->asString(), $result->getItems()[0]->ownerTargetId);
        Assert::assertSame('Payments Service', $result->getItems()[0]->projectName);
        Assert::assertNotNull($result->getItems()[0]->claimedAt);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function falls_back_to_unknown_project_name_when_the_payload_has_no_project_snapshot(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $runnerId = RunnerId::generate();

        $runner = Runner::register(
            id: $runnerId,
            organizationId: $organizationId,
            name: 'runner-fleet-01',
            status: RunnerStatusEnum::IDLE,
        );
        $this->runners->method('findByIdForOrganization')->willReturn($runner);

        $job = $this->createJob(
            RunnerJobOwnerId::generate(),
            RunnerJobOwnerTargetId::generate(),
            $organizationId,
            RunnerJobKindEnum::CHANGE,
            ['mode' => 'ai', 'prompt' => 'Apply the migration'],
        );
        $this->runnerJobs->method('getPaginatedListByClaimedBy')->willReturn(ListResponse::create([$job], 1, PaginationParameters::fromRequest()));

        // Act
        $result = ($this->handler)(new ListRunnerJobsQuery(
            runnerId: $runnerId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertSame('Unknown', $result->getItems()[0]->projectName);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_not_found_when_the_runner_does_not_exist_or_belongs_to_another_organization(): void
    {
        // Arrange: the repository scopes the lookup by organization at the query level, so
        // "doesn't exist" and "belongs to someone else" both surface as null here.
        $this->runners->method('findByIdForOrganization')->willReturn(null);

        // Assert
        $this->expectException(RunnerNotFoundException::class);

        // Act
        ($this->handler)(new ListRunnerJobsQuery(
            runnerId: RunnerId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->runners = $this->createStub(RunnerRepositoryInterface::class);
        $this->runnerJobs = $this->createMock(RunnerJobRepositoryInterface::class);
        $this->handler = new ListRunnerJobsHandler($this->runners, $this->runnerJobs);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createJob(
        RunnerJobOwnerId $ownerId,
        RunnerJobOwnerTargetId $ownerTargetId,
        OrganizationId $organizationId,
        RunnerJobKindEnum $kind,
        array $payload,
    ): RunnerJob {
        return RunnerJob::enqueue(
            id: RunnerJobId::generate(),
            ownerId: $ownerId,
            ownerTargetId: $ownerTargetId,
            organizationId: $organizationId,
            kind: $kind,
            ownerLabel: 'Bump acme/legacy-lib',
            mode: CriteriaModeEnum::AI,
            payload: $payload,
        );
    }
}
