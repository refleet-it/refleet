<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Application\Command\RecordShiftTargetResult;

use App\Shift\Shift\Application\Command\RecordShiftTargetResult\RecordShiftTargetResultCommand;
use App\Shift\Shift\Application\Command\RecordShiftTargetResult\RecordShiftTargetResultHandler;
use App\Shift\Shift\Domain\Shift\Enum\ShiftStatusEnum;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecordShiftTargetResultHandler::class)]
final class RecordShiftTargetResultHandlerTest extends TestCase
{
    private ShiftTargetRepositoryInterface&MockObject $shiftTargets;

    private ShiftRepositoryInterface&MockObject $shifts;

    private RecordShiftTargetResultHandler $handler;

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function is_a_silent_no_op_when_the_target_does_not_exist(): void
    {
        // Arrange
        $this->shiftTargets->method('findById')->willReturn(null);
        $this->shiftTargets->expects($this->never())->method('save');
        $this->shifts->expects($this->never())->method('findById');

        // Act
        ($this->handler)(new RecordShiftTargetResultCommand(
            shiftId: ShiftId::generate()->asString(),
            targetId: ShiftTargetId::generate()->asString(),
            organizationId: '22222222-2222-2222-2222-222222222222',
            success: true,
            summary: 'Applied',
            runnerName: 'runner-1',
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function records_a_successful_change(): void
    {
        // Arrange
        $target = $this->createMock(ShiftTarget::class);
        $target->expects($this->once())->method('recordChangeSuccess')->with('Bumped to v2', 'shift/bump-lib', 'runner-1', 'https://gitlab.example.com/acme/robots/-/merge_requests/9', '9');
        $this->shiftTargets->method('findById')->willReturn($target);
        $this->shiftTargets->expects($this->once())->method('save')->with($target);
        $this->shiftTargets->method('countByShiftIdAndStatuses')->willReturn(1);

        // Act
        ($this->handler)(new RecordShiftTargetResultCommand(
            shiftId: ShiftId::generate()->asString(),
            targetId: ShiftTargetId::generate()->asString(),
            organizationId: '22222222-2222-2222-2222-222222222222',
            success: true,
            summary: 'Bumped to v2',
            runnerName: 'runner-1',
            branchName: 'shift/bump-lib',
            mergeRequestUrl: 'https://gitlab.example.com/acme/robots/-/merge_requests/9',
            mergeRequestIid: '9',
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function records_a_failure_falling_back_to_the_summary_when_no_error_message_is_given(): void
    {
        // Arrange
        $target = $this->createMock(ShiftTarget::class);
        $target->expects($this->once())->method('recordChangeFailure')->with('Runner crashed', 'runner-1');
        $this->shiftTargets->method('findById')->willReturn($target);
        $this->shiftTargets->method('countByShiftIdAndStatuses')->willReturn(1);

        // Act
        ($this->handler)(new RecordShiftTargetResultCommand(
            shiftId: ShiftId::generate()->asString(),
            targetId: ShiftTargetId::generate()->asString(),
            organizationId: '22222222-2222-2222-2222-222222222222',
            success: false,
            summary: 'Runner crashed',
            runnerName: 'runner-1',
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function completes_the_shift_when_no_target_is_still_in_flight(): void
    {
        // Arrange
        $shiftId = ShiftId::generate();

        $target = $this->createStub(ShiftTarget::class);
        $this->shiftTargets->method('findById')->willReturn($target);
        $this->shiftTargets->method('countByShiftIdAndStatuses')->willReturn(0);

        $shift = $this->createMock(Shift::class);
        $shift->method('status')->willReturn(ShiftStatusEnum::APPLYING_CHANGE);
        $shift->expects($this->once())->method('complete');
        $this->shifts->method('findById')->willReturn($shift);
        $this->shifts->expects($this->once())->method('save')->with($shift);

        // Act
        ($this->handler)(new RecordShiftTargetResultCommand(
            shiftId: $shiftId->asString(),
            targetId: ShiftTargetId::generate()->asString(),
            organizationId: '22222222-2222-2222-2222-222222222222',
            success: true,
            summary: 'Applied',
            runnerName: 'runner-1',
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function does_not_complete_the_shift_while_targets_are_still_in_flight(): void
    {
        // Arrange
        $target = $this->createStub(ShiftTarget::class);
        $this->shiftTargets->method('findById')->willReturn($target);
        $this->shiftTargets->method('countByShiftIdAndStatuses')->willReturn(2);

        $this->shifts->expects($this->never())->method('findById');
        $this->shifts->expects($this->never())->method('save');

        // Act
        ($this->handler)(new RecordShiftTargetResultCommand(
            shiftId: ShiftId::generate()->asString(),
            targetId: ShiftTargetId::generate()->asString(),
            organizationId: '22222222-2222-2222-2222-222222222222',
            success: true,
            summary: 'Applied',
            runnerName: 'runner-1',
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function leaves_a_shift_that_is_not_applying_alone_when_its_last_target_settles(): void
    {
        // Arrange — a trial run on a draft, or a cancel that raced the report
        $target = $this->createStub(ShiftTarget::class);
        $this->shiftTargets->method('findById')->willReturn($target);
        $this->shiftTargets->method('countByShiftIdAndStatuses')->willReturn(0);

        $shift = $this->createMock(Shift::class);
        $shift->method('status')->willReturn(ShiftStatusEnum::DRAFT);
        $shift->expects($this->never())->method('complete');
        $this->shifts->method('findById')->willReturn($shift);
        $this->shifts->expects($this->never())->method('save');

        // Act
        ($this->handler)(new RecordShiftTargetResultCommand(
            shiftId: ShiftId::generate()->asString(),
            targetId: ShiftTargetId::generate()->asString(),
            organizationId: '22222222-2222-2222-2222-222222222222',
            success: true,
            summary: 'Applied',
            runnerName: 'runner-1',
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->shiftTargets = $this->createMock(ShiftTargetRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->handler = new RecordShiftTargetResultHandler($this->shiftTargets, $this->shifts);
    }
}
