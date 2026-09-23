<?php

declare(strict_types=1);

namespace App\Tests\Unit\Runner\Runner\Application\Command\CancelOwnerJobs;

use App\Runner\Runner\Application\Command\CancelOwnerJobs\CancelOwnerJobsCommand;
use App\Runner\Runner\Application\Command\CancelOwnerJobs\CancelOwnerJobsHandler;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobKindEnum;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobStatusEnum;
use App\Runner\Runner\Domain\RunnerJob\Model\RunnerJob;
use App\Runner\Runner\Domain\RunnerJob\Repository\RunnerJobRepositoryInterface;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerTargetId;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CancelOwnerJobsHandler::class)]
final class CancelOwnerJobsHandlerTest extends TestCase
{
    private RunnerJobRepositoryInterface&MockObject $runnerJobs;

    private CancelOwnerJobsHandler $handler;

    #[Test]
    public function cancels_every_non_terminal_job_for_the_owner(): void
    {
        // Arrange
        $ownerId = RunnerJobOwnerId::generate();
        $organizationId = OrganizationId::generate();

        $pending = $this->newJob($ownerId, $organizationId);
        $claimed = $this->newJob($ownerId, $organizationId);
        $claimed->claim('runner-1', 300);

        $this->runnerJobs
            ->expects($this->once())
            ->method('findNonTerminalByOwnerId')
            ->with($ownerId, $organizationId)
            ->willReturn([$pending, $claimed]);

        $this->runnerJobs
            ->expects($this->once())
            ->method('saveAll')
            ->with([$pending, $claimed]);

        // Act
        ($this->handler)(new CancelOwnerJobsCommand(
            ownerId: $ownerId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertSame(RunnerJobStatusEnum::CANCELLED, $pending->status());
        Assert::assertSame(RunnerJobStatusEnum::CANCELLED, $claimed->status());
    }

    #[Test]
    public function is_a_no_op_when_nothing_is_in_flight(): void
    {
        // Arrange
        $this->runnerJobs->method('findNonTerminalByOwnerId')->willReturn([]);
        $this->runnerJobs->expects($this->once())->method('saveAll')->with([]);

        // Act
        ($this->handler)(new CancelOwnerJobsCommand(
            ownerId: RunnerJobOwnerId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));

        // Assert — no exception
        Assert::assertTrue(true);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->runnerJobs = $this->createMock(RunnerJobRepositoryInterface::class);
        $this->handler = new CancelOwnerJobsHandler($this->runnerJobs);
    }

    private function newJob(RunnerJobOwnerId $ownerId, OrganizationId $organizationId): RunnerJob
    {
        return RunnerJob::enqueue(
            id: RunnerJobId::generate(),
            ownerId: $ownerId,
            ownerTargetId: RunnerJobOwnerTargetId::generate(),
            organizationId: $organizationId,
            kind: RunnerJobKindEnum::QUALIFICATION,
            ownerLabel: 'Bump acme/legacy-lib',
            mode: CriteriaModeEnum::AI,
            payload: ['mode' => 'ai'],
        );
    }
}
