<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Application\Command\RetryPipelineFix;

use App\Shared\Domain\ValueObject\ProjectSnapshot;
use App\Shift\Shift\Application\Command\RetryPipelineFix\RetryPipelineFixCommand;
use App\Shift\Shift\Application\Command\RetryPipelineFix\RetryPipelineFixHandler;
use App\Shift\Shift\Domain\Shift\Exception\ShiftArchivedException;
use App\Shift\Shift\Domain\Shift\Exception\ShiftNotFoundException;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\AccountId;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\PipelineFixOutcomeEnum;
use App\Shift\Shift\Domain\ShiftTarget\Enum\PipelineStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Exception\InvalidShiftTargetStateTransitionException;
use App\Shift\Shift\Domain\ShiftTarget\Exception\ShiftTargetNotFoundException;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ProjectId;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RetryPipelineFixHandler::class)]
final class RetryPipelineFixHandlerTest extends TestCase
{
    private const string JOB = '0199a000-0000-7000-8000-000000000001';

    private ShiftRepositoryInterface&MockObject $shifts;

    private ShiftTargetRepositoryInterface&MockObject $shiftTargets;

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function puts_a_target_that_gave_up_back_in_the_polls_queue(): void
    {
        // Arrange
        $shift = $this->shift();
        $target = $this->gaveUp($shift);
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);
        $this->shiftTargets->method('findById')->willReturn($target);
        $this->shiftTargets->expects($this->once())->method('save')->with($target);

        // Act
        $this->handle($shift, $target);

        // Assert
        Assert::assertSame(PipelineStatusEnum::WAITING, $target->pipelineStatus());
        Assert::assertSame(0, $target->pipelineFixAttempts());
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function refuses_a_target_whose_fix_did_not_give_up(): void
    {
        // Arrange
        $shift = $this->shift();
        $target = $this->openMergeRequest($shift);
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);
        $this->shiftTargets->method('findById')->willReturn($target);
        $this->shiftTargets->expects($this->never())->method('save');

        // Assert
        $this->expectException(InvalidShiftTargetStateTransitionException::class);

        // Act
        $this->handle($shift, $target);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function refuses_an_archived_shift(): void
    {
        // Arrange
        $shift = $this->shift();
        $target = $this->gaveUp($shift);
        $shift->archive(new \DateTimeImmutable());
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);
        $this->shiftTargets->expects($this->never())->method('save');

        // Assert
        $this->expectException(ShiftArchivedException::class);

        // Act
        $this->handle($shift, $target);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function a_shift_of_another_organization_is_not_found(): void
    {
        // Arrange
        $shift = $this->shift();
        $target = $this->gaveUp($shift);
        $this->shifts->method('findByIdForOrganization')->willReturn(null);

        // Assert
        $this->expectException(ShiftNotFoundException::class);

        // Act
        $this->handle($shift, $target);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function a_target_of_another_shift_is_not_found(): void
    {
        // Arrange
        $shift = $this->shift();
        $target = $this->gaveUp($this->shift());
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);
        $this->shiftTargets->method('findById')->willReturn($target);

        // Assert
        $this->expectException(ShiftTargetNotFoundException::class);

        // Act
        $this->handle($shift, $target);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftTargets = $this->createMock(ShiftTargetRepositoryInterface::class);
    }

    private function handle(Shift $shift, ShiftTarget $target): void
    {
        new RetryPipelineFixHandler($this->shifts, $this->shiftTargets)(new RetryPipelineFixCommand(
            shiftId: $shift->id()->asString(),
            shiftTargetId: $target->id()->asString(),
            organizationId: $shift->organizationId()->asString(),
        ));
    }

    private function shift(): Shift
    {
        return Shift::draft(ShiftId::generate(), OrganizationId::generate(), 'Bump legacy-lib', null, AccountId::generate(), null);
    }

    private function openMergeRequest(Shift $shift): ShiftTarget
    {
        $target = ShiftTarget::create(
            ShiftTargetId::generate(),
            $shift->id(),
            $shift->organizationId(),
            ProjectId::generate(),
            new ProjectSnapshot('10', 'acme/payments', 'payments', 'main'),
        );
        $target->startChange('0199a000-0000-7000-8000-000000000000');
        $target->recordChangeSuccess('done', 'refleet/change-1', 'runner', 'https://gitlab.com/a/b/-/merge_requests/9', '9', 'abc', true);

        return $target;
    }

    private function gaveUp(Shift $shift): ShiftTarget
    {
        $target = $this->openMergeRequest($shift);
        $target->startPipelineFix(self::JOB, '77');
        $target->recordPipelineFixResult(self::JOB, PipelineFixOutcomeEnum::NOT_FIXED, 'Only passes by skipping the test.');

        return $target;
    }
}
