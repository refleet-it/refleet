<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Application\Command\StartShiftTargetChange;

use App\Shared\Domain\ValueObject\ProjectSnapshot;
use App\Shift\Shift\Application\Command\StartShiftTargetChange\StartShiftTargetChangeCommand;
use App\Shift\Shift\Application\Command\StartShiftTargetChange\StartShiftTargetChangeHandler;
use App\Shift\Shift\Domain\Shift\Enum\ShiftStatusEnum;
use App\Shift\Shift\Domain\Shift\Exception\ShiftNotFoundException;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\Service\ShiftJobPayloadFactory;
use App\Shift\Shift\Domain\Shift\ValueObject\AccountId;
use App\Shift\Shift\Domain\Shift\ValueObject\ChangeCriteria;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Exception\InvalidShiftTargetStateTransitionException;
use App\Shift\Shift\Domain\ShiftTarget\Exception\ShiftTargetNotFoundException;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ProjectId;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use App\Shift\Shift\Infrastructure\Bus\RunnerJobRequested\RunnerJobRequestedMessage;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(StartShiftTargetChangeHandler::class)]
final class StartShiftTargetChangeHandlerTest extends TestCase
{
    private ShiftRepositoryInterface&MockObject $shifts;

    private ShiftTargetRepositoryInterface&MockObject $shiftTargets;

    private MessageBusInterface&MockObject $bus;

    private StartShiftTargetChangeHandler $handler;

    #[Test]
    public function trial_runs_a_pending_target_of_a_draft_and_leaves_the_shift_a_draft(): void
    {
        // Arrange
        $shift = $this->draftShift();
        $target = $this->target($shift);
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);
        $this->shiftTargets->method('findById')->willReturn($target);
        $this->shifts->expects($this->once())->method('save')->with($shift);
        $this->shiftTargets->expects($this->once())->method('save')->with($target);

        $dispatched = null;
        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (RunnerJobRequestedMessage $message) use (&$dispatched): Envelope {
                $dispatched = $message;

                return new Envelope(new \stdClass());
            });

        // Act
        ($this->handler)(new StartShiftTargetChangeCommand(
            shiftId: $shift->id()->asString(),
            shiftTargetId: $target->id()->asString(),
            organizationId: $shift->organizationId()->asString(),
        ));

        // Assert
        Assert::assertSame(ShiftStatusEnum::DRAFT, $shift->status());
        Assert::assertSame(ShiftTargetStatusEnum::CHANGE_IN_PROGRESS, $target->status());
        Assert::assertNotNull($dispatched);
        Assert::assertSame($target->runnerJobId(), $dispatched->jobId);
        Assert::assertSame($shift->id()->asString(), $dispatched->ownerId);
        Assert::assertSame($target->id()->asString(), $dispatched->ownerTargetId);
        Assert::assertSame('Bump acme/legacy-lib', $dispatched->ownerLabel);
        Assert::assertSame('change', $dispatched->kind);
        Assert::assertStringContainsString('Bump acme/legacy-lib to ^3.0', (string) $dispatched->payload['prompt']);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function reruns_a_settled_target_and_reopens_a_completed_shift(): void
    {
        // Arrange
        $shift = $this->draftShift();
        $shift->startChange();
        $shift->complete();

        $target = $this->target($shift);
        $target->startChange('job-1');
        $target->recordChangeFailure('boom', 'runner-1');
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);
        $this->shiftTargets->method('findById')->willReturn($target);
        $this->bus->method('dispatch')->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        // Act
        ($this->handler)(new StartShiftTargetChangeCommand(
            shiftId: $shift->id()->asString(),
            shiftTargetId: $target->id()->asString(),
            organizationId: $shift->organizationId()->asString(),
        ));

        // Assert
        Assert::assertSame(ShiftStatusEnum::APPLYING_CHANGE, $shift->status());
        Assert::assertSame(ShiftTargetStatusEnum::CHANGE_IN_PROGRESS, $target->status());
        Assert::assertNotSame('job-1', $target->runnerJobId());
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function refuses_a_target_whose_job_is_still_running(): void
    {
        // Arrange
        $shift = $this->draftShift();
        $target = $this->target($shift);
        $target->startChange('job-1');
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);
        $this->shiftTargets->method('findById')->willReturn($target);
        $this->bus->expects($this->never())->method('dispatch');
        $this->shiftTargets->expects($this->never())->method('save');

        // Assert
        $this->expectException(InvalidShiftTargetStateTransitionException::class);

        // Act
        ($this->handler)(new StartShiftTargetChangeCommand(
            shiftId: $shift->id()->asString(),
            shiftTargetId: $target->id()->asString(),
            organizationId: $shift->organizationId()->asString(),
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_not_found_for_a_target_of_another_shift(): void
    {
        // Arrange
        $shift = $this->draftShift();
        $foreign = $this->target($this->draftShift());
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);
        $this->shiftTargets->method('findById')->willReturn($foreign);

        // Assert
        $this->expectException(ShiftTargetNotFoundException::class);

        // Act
        ($this->handler)(new StartShiftTargetChangeCommand(
            shiftId: $shift->id()->asString(),
            shiftTargetId: $foreign->id()->asString(),
            organizationId: $shift->organizationId()->asString(),
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_not_found_when_the_shift_is_not_owned(): void
    {
        // Arrange
        $this->shifts->method('findByIdForOrganization')->willReturn(null);

        // Assert
        $this->expectException(ShiftNotFoundException::class);

        // Act
        ($this->handler)(new StartShiftTargetChangeCommand(
            shiftId: ShiftId::generate()->asString(),
            shiftTargetId: ShiftTargetId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftTargets = $this->createMock(ShiftTargetRepositoryInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->handler = new StartShiftTargetChangeHandler($this->shifts, $this->shiftTargets, $this->bus, new ShiftJobPayloadFactory());
    }

    private function draftShift(): Shift
    {
        $shift = Shift::draft(
            id: ShiftId::generate(),
            organizationId: OrganizationId::generate(),
            title: 'Bump acme/legacy-lib',
            description: null,
            createdBy: AccountId::generate(),
            qualificationId: null,
        );
        $shift->defineChange(ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0'));

        return $shift;
    }

    private function target(Shift $shift): ShiftTarget
    {
        return ShiftTarget::create(
            id: ShiftTargetId::generate(),
            shiftId: $shift->id(),
            organizationId: $shift->organizationId(),
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('48210942', 'backend-team/payments-service', 'Payments Service', 'main'),
        );
    }
}
