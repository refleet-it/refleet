<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Application\Command\RecordQualificationTargetResult;

use App\Qualification\Qualification\Application\Command\RecordQualificationTargetResult\RecordQualificationTargetResultCommand;
use App\Qualification\Qualification\Application\Command\RecordQualificationTargetResult\RecordQualificationTargetResultHandler;
use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\AccountId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationCriteria;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Model\QualificationTarget;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\ProjectId;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\QualificationTargetId;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecordQualificationTargetResultHandler::class)]
final class RecordQualificationTargetResultHandlerTest extends TestCase
{
    private QualificationTargetRepositoryInterface&MockObject $qualificationTargets;

    private QualificationRepositoryInterface&MockObject $qualifications;

    private RecordQualificationTargetResultHandler $handler;

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function is_a_silent_no_op_when_the_target_no_longer_exists(): void
    {
        // Arrange
        $this->qualificationTargets->method('findById')->willReturn(null);
        $this->qualificationTargets->expects($this->never())->method('save');
        $this->qualifications->expects($this->never())->method('findById');

        // Act — must not throw
        ($this->handler)(new RecordQualificationTargetResultCommand(
            qualificationId: QualificationId::generate()->asString(),
            targetId: QualificationTargetId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
            success: true,
            summary: 'Qualified',
            runnerName: 'runner-1',
        ));

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function records_success_and_completes_the_qualification_when_nothing_remains(): void
    {
        // Arrange
        $target = $this->startedTarget();
        $this->qualificationTargets->method('findById')->willReturn($target);
        $this->qualificationTargets->expects($this->once())->method('save')->with($target);
        $this->qualificationTargets->method('countByQualificationIdAndStatuses')->willReturn(0);

        $qualification = $this->runningQualification($target->qualificationId());
        $this->qualifications->method('findById')->willReturn($qualification);
        $this->qualifications->expects($this->once())->method('save')->with($qualification);

        // Act
        ($this->handler)(new RecordQualificationTargetResultCommand(
            qualificationId: $target->qualificationId()->asString(),
            targetId: $target->id()->asString(),
            organizationId: $target->organizationId()->asString(),
            success: true,
            summary: 'Depends on acme/legacy-lib',
            runnerName: 'runner-1',
            score: 5,
        ));

        // Assert
        Assert::assertSame('qualified', $target->status()->value);
        Assert::assertSame(5, $target->score());
        Assert::assertSame('completed', $qualification->status()->value);
    }

    #[Test]
    public function a_score_below_the_threshold_records_a_not_qualified_outcome(): void
    {
        // Arrange
        $target = $this->startedTarget();
        $this->qualificationTargets->method('findById')->willReturn($target);
        $this->qualificationTargets->expects($this->once())->method('save')->with($target);
        $this->qualificationTargets->method('countByQualificationIdAndStatuses')->willReturn(1);
        $this->qualifications->expects($this->never())->method('save');

        // Act
        ($this->handler)(new RecordQualificationTargetResultCommand(
            qualificationId: $target->qualificationId()->asString(),
            targetId: $target->id()->asString(),
            organizationId: $target->organizationId()->asString(),
            success: true,
            summary: 'Only an indirect hint in a lock file',
            runnerName: 'runner-1',
            score: 3,
        ));

        // Assert
        Assert::assertSame('not_qualified', $target->status()->value);
        Assert::assertSame(3, $target->score());
    }

    #[Test]
    public function a_success_without_a_score_is_recorded_as_a_failed_run(): void
    {
        // Arrange
        $target = $this->startedTarget();
        $this->qualificationTargets->method('findById')->willReturn($target);
        $this->qualificationTargets->expects($this->once())->method('save')->with($target);
        $this->qualificationTargets->method('countByQualificationIdAndStatuses')->willReturn(1);
        $this->qualifications->expects($this->never())->method('save');

        // Act
        ($this->handler)(new RecordQualificationTargetResultCommand(
            qualificationId: $target->qualificationId()->asString(),
            targetId: $target->id()->asString(),
            organizationId: $target->organizationId()->asString(),
            success: true,
            summary: 'Looks fine',
            runnerName: 'runner-1',
        ));

        // Assert
        Assert::assertSame('failed', $target->status()->value);
        Assert::assertNull($target->score());
        Assert::assertSame('Runner reported success without a qualification score', $target->summary());
    }

    #[Test]
    public function records_failure(): void
    {
        // Arrange
        $target = $this->startedTarget();
        $this->qualificationTargets->method('findById')->willReturn($target);
        $this->qualificationTargets->expects($this->once())->method('save')->with($target);
        $this->qualificationTargets->method('countByQualificationIdAndStatuses')->willReturn(1);

        $this->qualifications->expects($this->never())->method('save');

        // Act
        ($this->handler)(new RecordQualificationTargetResultCommand(
            qualificationId: $target->qualificationId()->asString(),
            targetId: $target->id()->asString(),
            organizationId: $target->organizationId()->asString(),
            success: false,
            summary: 'Runner crashed',
            runnerName: 'runner-1',
        ));

        // Assert
        Assert::assertSame('failed', $target->status()->value);
        Assert::assertSame('Runner crashed', $target->summary());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function does_not_complete_the_qualification_while_targets_remain(): void
    {
        // Arrange
        $target = $this->startedTarget();
        $this->qualificationTargets->method('findById')->willReturn($target);
        $this->qualificationTargets->method('countByQualificationIdAndStatuses')->willReturn(2);

        $this->qualifications->expects($this->never())->method('findById');
        $this->qualifications->expects($this->never())->method('save');

        // Act
        ($this->handler)(new RecordQualificationTargetResultCommand(
            qualificationId: $target->qualificationId()->asString(),
            targetId: $target->id()->asString(),
            organizationId: $target->organizationId()->asString(),
            success: true,
            summary: 'Qualified',
            runnerName: 'runner-1',
            score: 5,
        ));
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function swallows_the_race_when_the_qualification_was_already_cancelled_in_parallel(): void
    {
        // Arrange
        $target = $this->startedTarget();
        $this->qualificationTargets->method('findById')->willReturn($target);
        $this->qualificationTargets->method('countByQualificationIdAndStatuses')->willReturn(0);

        $qualification = $this->runningQualification($target->qualificationId());
        $qualification->cancel('cancelled mid-flight');
        $this->qualifications->method('findById')->willReturn($qualification);
        $this->qualifications->expects($this->never())->method('save');

        // Act — must not throw despite the underlying InvalidQualificationStateTransitionException
        ($this->handler)(new RecordQualificationTargetResultCommand(
            qualificationId: $target->qualificationId()->asString(),
            targetId: $target->id()->asString(),
            organizationId: $target->organizationId()->asString(),
            success: true,
            summary: 'Qualified',
            runnerName: 'runner-1',
            score: 5,
        ));

        Assert::assertSame('cancelled', $qualification->status()->value);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->qualificationTargets = $this->createMock(QualificationTargetRepositoryInterface::class);
        $this->qualifications = $this->createMock(QualificationRepositoryInterface::class);
        $this->handler = new RecordQualificationTargetResultHandler($this->qualificationTargets, $this->qualifications);
    }

    private function startedTarget(): QualificationTarget
    {
        $target = QualificationTarget::create(
            id: QualificationTargetId::generate(),
            qualificationId: QualificationId::generate(),
            organizationId: OrganizationId::generate(),
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('1', 'group/project', 'Project', 'main'),
        );
        $target->start('runner-job-1');

        return $target;
    }

    private function runningQualification(QualificationId $id): Qualification
    {
        $qualification = Qualification::draft(
            id: $id,
            organizationId: OrganizationId::generate(),
            title: 'Test qualification',
            description: null,
            createdBy: AccountId::generate(),
            criteria: QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?'),
        );
        $qualification->start();

        return $qualification;
    }
}
