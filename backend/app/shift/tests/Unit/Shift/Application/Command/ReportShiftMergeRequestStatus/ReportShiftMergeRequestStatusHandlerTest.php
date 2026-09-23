<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Application\Command\ReportShiftMergeRequestStatus;

use App\Shift\Shift\Application\Command\ReportShiftMergeRequestStatus\ReportShiftMergeRequestStatusCommand;
use App\Shift\Shift\Application\Command\ReportShiftMergeRequestStatus\ReportShiftMergeRequestStatusHandler;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Exception\ShiftTargetNotFoundException;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReportShiftMergeRequestStatusHandler::class)]
final class ReportShiftMergeRequestStatusHandlerTest extends TestCase
{
    private ShiftTargetRepositoryInterface&MockObject $shiftTargets;

    private ShiftRepositoryInterface&MockObject $shifts;

    private ReportShiftMergeRequestStatusHandler $handler;

    #[Test]
    public function records_the_merge_request_as_opened_without_checking_completion(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $target = $this->createMock(ShiftTarget::class);
        $target->method('organizationId')->willReturn($organizationId);
        $target->expects($this->once())->method('recordMergeRequestOpened')->with('https://gitlab.example/mr/1', '1');
        $this->shiftTargets->method('findById')->willReturn($target);
        $this->shiftTargets->expects($this->once())->method('save')->with($target);

        $this->shifts->expects($this->never())->method('findById');

        // Act
        ($this->handler)(new ReportShiftMergeRequestStatusCommand(
            shiftTargetId: ShiftTargetId::generate()->asString(),
            organizationId: $organizationId->asString(),
            status: 'opened',
            url: 'https://gitlab.example/mr/1',
            externalIid: '1',
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function records_merged_and_completes_the_shift_when_no_target_is_still_in_flight(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $shiftId = ShiftId::generate();

        $target = $this->createMock(ShiftTarget::class);
        $target->method('organizationId')->willReturn($organizationId);
        $target->method('shiftId')->willReturn($shiftId);
        $target->expects($this->once())->method('recordMergeRequestMerged');
        $this->shiftTargets->method('findById')->willReturn($target);
        $this->shiftTargets->method('countByShiftIdAndStatuses')->willReturn(0);

        $shift = $this->createMock(Shift::class);
        $shift->expects($this->once())->method('complete');
        $this->shifts->method('findById')->willReturn($shift);
        $this->shifts->expects($this->once())->method('save')->with($shift);

        // Act
        ($this->handler)(new ReportShiftMergeRequestStatusCommand(
            shiftTargetId: ShiftTargetId::generate()->asString(),
            organizationId: $organizationId->asString(),
            status: 'merged',
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function does_not_check_completion_when_targets_are_still_in_flight(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $target = $this->createMock(ShiftTarget::class);
        $target->method('organizationId')->willReturn($organizationId);
        $target->method('shiftId')->willReturn(ShiftId::generate());
        $target->expects($this->once())->method('recordMergeRequestClosed');
        $this->shiftTargets->method('findById')->willReturn($target);
        $this->shiftTargets->method('countByShiftIdAndStatuses')->willReturn(2);

        $this->shifts->expects($this->never())->method('save');

        // Act
        ($this->handler)(new ReportShiftMergeRequestStatusCommand(
            shiftTargetId: ShiftTargetId::generate()->asString(),
            organizationId: $organizationId->asString(),
            status: 'closed',
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_not_found_when_the_target_belongs_to_another_organization(): void
    {
        // Arrange
        $target = $this->createStub(ShiftTarget::class);
        $target->method('organizationId')->willReturn(OrganizationId::generate());
        $this->shiftTargets->method('findById')->willReturn($target);

        // Assert
        $this->expectException(ShiftTargetNotFoundException::class);

        // Act
        ($this->handler)(new ReportShiftMergeRequestStatusCommand(
            shiftTargetId: ShiftTargetId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
            status: 'merged',
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function rejects_an_unknown_status(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $target = $this->createStub(ShiftTarget::class);
        $target->method('organizationId')->willReturn($organizationId);
        $this->shiftTargets->method('findById')->willReturn($target);

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        ($this->handler)(new ReportShiftMergeRequestStatusCommand(
            shiftTargetId: ShiftTargetId::generate()->asString(),
            organizationId: $organizationId->asString(),
            status: 'approved',
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->shiftTargets = $this->createMock(ShiftTargetRepositoryInterface::class);
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->handler = new ReportShiftMergeRequestStatusHandler($this->shiftTargets, $this->shifts);
    }
}
