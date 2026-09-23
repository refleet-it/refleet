<?php

declare(strict_types=1);

namespace App\Tests\Unit\Runner\Runner\Application\Command\ReportRunnerJobResult;

use App\Runner\Runner\Application\Command\ReportRunnerJobResult\ReportRunnerJobResultCommand;
use App\Runner\Runner\Application\Command\ReportRunnerJobResult\ReportRunnerJobResultHandler;
use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobKindEnum;
use App\Runner\Runner\Domain\RunnerJob\Exception\RunnerJobNotClaimedByCallerException;
use App\Runner\Runner\Domain\RunnerJob\Exception\RunnerJobNotFoundException;
use App\Runner\Runner\Domain\RunnerJob\Model\RunnerJob;
use App\Runner\Runner\Domain\RunnerJob\Repository\RunnerJobRepositoryInterface;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerTargetId;
use App\Runner\Runner\Infrastructure\Bus\QualificationTargetResultReported\QualificationTargetResultReportedMessage;
use App\Runner\Runner\Infrastructure\Bus\ShiftTargetResultReported\ShiftTargetResultReportedMessage;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(ReportRunnerJobResultHandler::class)]
final class ReportRunnerJobResultHandlerTest extends TestCase
{
    private RunnerJobRepositoryInterface&MockObject $runnerJobs;

    private MessageBusInterface&MockObject $bus;

    private ReportRunnerJobResultHandler $handler;

    #[Test]
    public function on_success_records_a_qualification_target_result_when_the_job_is_a_qualification_job(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $job = $this->claimedJob($organizationId, RunnerJobKindEnum::QUALIFICATION);
        $this->runnerJobs->method('findById')->willReturn($job);
        $this->runnerJobs->expects($this->once())->method('save')->with($job);

        $dispatched = null;
        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(QualificationTargetResultReportedMessage::class))
            ->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
                $dispatched = $message;

                return new Envelope($message);
            });

        // Act
        $result = ($this->handler)(new ReportRunnerJobResultCommand(
            jobId: $job->id()->asString(),
            runnerId: 'runner-1',
            organizationId: $organizationId->asString(),
            outcome: 'success',
            summary: 'Qualified',
            score: 4,
        ));

        // Assert
        Assert::assertInstanceOf(QualificationTargetResultReportedMessage::class, $dispatched);
        Assert::assertSame($job->ownerId()->asString(), $dispatched->qualificationId);
        Assert::assertSame($job->ownerTargetId()->asString(), $dispatched->targetId);
        Assert::assertSame($organizationId->asString(), $dispatched->organizationId);
        Assert::assertTrue($dispatched->success);
        Assert::assertSame('Qualified', $dispatched->summary);
        Assert::assertSame('runner-1', $dispatched->runnerName);
        Assert::assertSame(4, $dispatched->score);
        Assert::assertSame('succeeded', $result->status);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function on_success_records_a_shift_target_result_when_the_job_is_a_change_job(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $job = $this->claimedJob($organizationId, RunnerJobKindEnum::CHANGE);
        $this->runnerJobs->method('findById')->willReturn($job);
        $this->runnerJobs->method('save');

        $dispatched = null;
        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ShiftTargetResultReportedMessage::class))
            ->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
                $dispatched = $message;

                return new Envelope($message);
            });

        // Act
        ($this->handler)(new ReportRunnerJobResultCommand(
            jobId: $job->id()->asString(),
            runnerId: 'runner-1',
            organizationId: $organizationId->asString(),
            outcome: 'success',
            summary: 'Applied',
            branchName: 'refleet/apply-change',
            mergeRequestUrl: 'https://gitlab.example.com/acme/robots/-/merge_requests/9',
            mergeRequestIid: '9',
        ));

        // Assert
        Assert::assertInstanceOf(ShiftTargetResultReportedMessage::class, $dispatched);
        Assert::assertSame($job->ownerId()->asString(), $dispatched->shiftId);
        Assert::assertSame($job->ownerTargetId()->asString(), $dispatched->targetId);
        Assert::assertTrue($dispatched->success);
        Assert::assertSame('refleet/apply-change', $dispatched->branchName);
        Assert::assertSame('https://gitlab.example.com/acme/robots/-/merge_requests/9', $dispatched->mergeRequestUrl);
        Assert::assertSame('9', $dispatched->mergeRequestIid);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function on_failure_dispatches_with_success_false_and_the_error_message(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $job = $this->claimedJob($organizationId, RunnerJobKindEnum::QUALIFICATION);
        $this->runnerJobs->method('findById')->willReturn($job);
        $this->runnerJobs->method('save');

        $dispatched = null;
        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
                $dispatched = $message;

                return new Envelope($message);
            });

        // Act
        $result = ($this->handler)(new ReportRunnerJobResultCommand(
            jobId: $job->id()->asString(),
            runnerId: 'runner-1',
            organizationId: $organizationId->asString(),
            outcome: 'failure',
            summary: 'Clone failed',
            errorMessage: 'Clone failed',
        ));

        // Assert
        Assert::assertInstanceOf(QualificationTargetResultReportedMessage::class, $dispatched);
        Assert::assertFalse($dispatched->success);
        Assert::assertSame('Clone failed', $dispatched->errorMessage);
        Assert::assertSame('failed', $result->status);
    }

    #[Test]
    public function a_non_claimed_job_short_circuits_and_never_touches_the_bus(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $job = $this->claimedJob($organizationId, RunnerJobKindEnum::QUALIFICATION);
        $job->reportSuccess('first report'); // already claimed by the helper — settle it once, simulating a stale second report
        $this->runnerJobs->method('findById')->willReturn($job);

        $this->bus->expects($this->never())->method('dispatch');
        $this->runnerJobs->expects($this->never())->method('save');

        // Act
        $result = ($this->handler)(new ReportRunnerJobResultCommand(
            jobId: $job->id()->asString(),
            runnerId: 'runner-1',
            organizationId: $organizationId->asString(),
            outcome: 'success',
            summary: 'second report, should be ignored',
        ));

        // Assert — idempotent: returns the ALREADY recorded result, not the new one
        Assert::assertSame('succeeded', $result->status);
        Assert::assertSame('first report', $result->resultSummary);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_when_the_calling_runner_did_not_claim_the_job(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $job = $this->claimedJob($organizationId, RunnerJobKindEnum::QUALIFICATION);
        $this->runnerJobs->method('findById')->willReturn($job);

        // Assert
        $this->expectException(RunnerJobNotClaimedByCallerException::class);

        // Act
        ($this->handler)(new ReportRunnerJobResultCommand(
            jobId: $job->id()->asString(),
            runnerId: 'a-different-runner',
            organizationId: $organizationId->asString(),
            outcome: 'success',
            summary: 'should not be recorded',
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_not_found_when_the_job_does_not_exist(): void
    {
        // Arrange
        $this->runnerJobs->method('findById')->willReturn(null);

        // Assert
        $this->expectException(RunnerJobNotFoundException::class);

        // Act
        ($this->handler)(new ReportRunnerJobResultCommand(
            jobId: RunnerJobId::generate()->asString(),
            runnerId: 'runner-1',
            organizationId: OrganizationId::generate()->asString(),
            outcome: 'success',
            summary: 'irrelevant',
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_not_found_when_the_job_belongs_to_another_organization(): void
    {
        // Arrange
        $job = $this->claimedJob(OrganizationId::generate(), RunnerJobKindEnum::QUALIFICATION);
        $this->runnerJobs->method('findById')->willReturn($job);

        // Assert
        $this->expectException(RunnerJobNotFoundException::class);

        // Act
        ($this->handler)(new ReportRunnerJobResultCommand(
            jobId: $job->id()->asString(),
            runnerId: 'runner-1',
            organizationId: OrganizationId::generate()->asString(),
            outcome: 'success',
            summary: 'irrelevant',
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->runnerJobs = $this->createMock(RunnerJobRepositoryInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->handler = new ReportRunnerJobResultHandler($this->runnerJobs, $this->bus);
    }

    private function claimedJob(OrganizationId $organizationId, RunnerJobKindEnum $kind): RunnerJob
    {
        $job = RunnerJob::enqueue(
            id: RunnerJobId::generate(),
            ownerId: RunnerJobOwnerId::generate(),
            ownerTargetId: RunnerJobOwnerTargetId::generate(),
            organizationId: $organizationId,
            kind: $kind,
            ownerLabel: 'Bump acme/legacy-lib',
            mode: CriteriaModeEnum::AI,
            payload: ['mode' => 'ai'],
        );
        $job->claim('runner-1', 300);

        return $job;
    }
}
