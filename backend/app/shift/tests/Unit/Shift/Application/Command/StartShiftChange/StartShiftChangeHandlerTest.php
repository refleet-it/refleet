<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Application\Command\StartShiftChange;

use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use App\Shift\Shift\Application\Command\StartShiftChange\StartShiftChangeCommand;
use App\Shift\Shift\Application\Command\StartShiftChange\StartShiftChangeHandler;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\Service\ShiftJobPayloadFactory;
use App\Shift\Shift\Domain\Shift\ValueObject\ChangeCriteria;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
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

#[CoversClass(StartShiftChangeHandler::class)]
final class StartShiftChangeHandlerTest extends TestCase
{
    private ShiftRepositoryInterface&MockObject $shifts;

    private ShiftTargetRepositoryInterface&MockObject $shiftTargets;

    private MessageBusInterface&MockObject $bus;

    private StartShiftChangeHandler $handler;

    #[Test]
    public function starts_the_change_and_enqueues_a_job_per_pending_target(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $shiftId = ShiftId::generate();
        $targetId = ShiftTargetId::generate();

        $shift = $this->createMock(Shift::class);
        $shift->method('id')->willReturn($shiftId);
        $shift->method('title')->willReturn('Bump acme/legacy-lib');
        $shift->method('organizationId')->willReturn($organizationId);
        $shift->method('changeCriteria')->willReturn(ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0'));
        $shift->expects($this->once())->method('startChange');
        $shift->expects($this->never())->method('complete');
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);
        $this->shifts->expects($this->once())->method('save')->with($shift);

        $target = $this->createMock(ShiftTarget::class);
        $target->method('id')->willReturn($targetId);
        $target->method('projectSnapshot')->willReturn(new ProjectSnapshot('48210942', 'backend-team/payments-service', 'Payments Service', 'main'));
        $publishedJobId = null;
        $target->expects($this->once())->method('startChange')->with($this->callback(static function (string $jobId) use (&$publishedJobId): bool {
            $publishedJobId = $jobId;

            return true;
        }));
        $this->shiftTargets->method('findByShiftIdAndStatuses')->willReturn([$target]);

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (RunnerJobRequestedMessage $command) use ($shiftId, $targetId): Envelope {
                Assert::assertSame($shiftId->asString(), $command->ownerId);
                Assert::assertSame($targetId->asString(), $command->ownerTargetId);
                Assert::assertSame('Bump acme/legacy-lib', $command->ownerLabel);
                Assert::assertSame('change', $command->kind);
                Assert::assertSame('ai', $command->mode);
                Assert::assertSame('claude', $command->engine);
                Assert::assertStringContainsString('Bump acme/legacy-lib to ^3.0', (string) $command->payload['prompt']);
                Assert::assertSame('backend-team/payments-service', $command->payload['project']['path']);

                return new Envelope(new \stdClass());
            });

        $this->shiftTargets->expects($this->once())->method('saveAll')->with([$target]);

        // Act
        ($this->handler)(new StartShiftChangeCommand(
            shiftId: $shiftId->asString(),
            organizationId: $organizationId->asString(),
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function carries_the_criteria_model_override_in_the_enqueued_job_payload(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $shiftId = ShiftId::generate();
        $targetId = ShiftTargetId::generate();

        $shift = $this->createStub(Shift::class);
        $shift->method('id')->willReturn($shiftId);
        $shift->method('title')->willReturn('AI change');
        $shift->method('organizationId')->willReturn($organizationId);
        $shift->method('changeCriteria')->willReturn(ChangeCriteria::ai('Apply the migration', 'claude-opus-5'));
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);

        $target = $this->createStub(ShiftTarget::class);
        $target->method('id')->willReturn($targetId);
        $target->method('projectSnapshot')->willReturn(new ProjectSnapshot('48210942', 'backend-team/payments-service', 'Payments Service', 'main'));
        $this->shiftTargets->method('findByShiftIdAndStatuses')->willReturn([$target]);

        $dispatchedCommand = null;
        $this->bus
            ->method('dispatch')
            ->willReturnCallback(static function (RunnerJobRequestedMessage $command) use (&$dispatchedCommand): Envelope {
                $dispatchedCommand = $command;

                return new Envelope(new \stdClass());
            });

        // Act
        ($this->handler)(new StartShiftChangeCommand(
            shiftId: $shiftId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertSame('claude-opus-5', $dispatchedCommand->payload['model']);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function defaults_the_ai_job_payload_engine_to_claude_when_none_was_selected(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $shiftId = ShiftId::generate();
        $targetId = ShiftTargetId::generate();

        $shift = $this->createStub(Shift::class);
        $shift->method('id')->willReturn($shiftId);
        $shift->method('title')->willReturn('AI change');
        $shift->method('organizationId')->willReturn($organizationId);
        $shift->method('changeCriteria')->willReturn(ChangeCriteria::ai('Apply the migration'));
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);

        $target = $this->createStub(ShiftTarget::class);
        $target->method('id')->willReturn($targetId);
        $target->method('projectSnapshot')->willReturn(new ProjectSnapshot('48210942', 'backend-team/payments-service', 'Payments Service', 'main'));
        $this->shiftTargets->method('findByShiftIdAndStatuses')->willReturn([$target]);

        $dispatchedCommand = null;
        $this->bus
            ->method('dispatch')
            ->willReturnCallback(static function (RunnerJobRequestedMessage $command) use (&$dispatchedCommand): Envelope {
                $dispatchedCommand = $command;

                return new Envelope(new \stdClass());
            });

        // Act
        ($this->handler)(new StartShiftChangeCommand(
            shiftId: $shiftId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertSame('claude', $dispatchedCommand->payload['engine']);
        Assert::assertSame('claude', $dispatchedCommand->engine);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function carries_the_selected_kiro_engine_in_the_enqueued_ai_job_payload(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $shiftId = ShiftId::generate();
        $targetId = ShiftTargetId::generate();

        $shift = $this->createStub(Shift::class);
        $shift->method('id')->willReturn($shiftId);
        $shift->method('title')->willReturn('AI change');
        $shift->method('organizationId')->willReturn($organizationId);
        $shift->method('changeCriteria')->willReturn(ChangeCriteria::ai('Apply the migration', null, CriteriaEngineEnum::KIRO));
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);

        $target = $this->createStub(ShiftTarget::class);
        $target->method('id')->willReturn($targetId);
        $target->method('projectSnapshot')->willReturn(new ProjectSnapshot('48210942', 'backend-team/payments-service', 'Payments Service', 'main'));
        $this->shiftTargets->method('findByShiftIdAndStatuses')->willReturn([$target]);

        $dispatchedCommand = null;
        $this->bus
            ->method('dispatch')
            ->willReturnCallback(static function (RunnerJobRequestedMessage $command) use (&$dispatchedCommand): Envelope {
                $dispatchedCommand = $command;

                return new Envelope(new \stdClass());
            });

        // Act
        ($this->handler)(new StartShiftChangeCommand(
            shiftId: $shiftId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertSame('kiro', $dispatchedCommand->payload['engine']);
        Assert::assertSame('kiro', $dispatchedCommand->engine);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function completes_the_shift_immediately_when_no_target_is_still_pending(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $shiftId = ShiftId::generate();

        $shift = $this->createMock(Shift::class);
        $shift->method('id')->willReturn($shiftId);
        $shift->method('organizationId')->willReturn($organizationId);
        $shift->expects($this->once())->method('startChange');
        $shift->expects($this->once())->method('complete');
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);
        $this->shifts->expects($this->once())->method('save')->with($shift);

        $this->shiftTargets->method('findByShiftIdAndStatuses')->willReturn([]);
        $this->shiftTargets->method('countByShiftIdAndStatuses')->willReturn(0);
        $this->shiftTargets->expects($this->never())->method('saveAll');
        $this->bus->expects($this->never())->method('dispatch');

        // Act
        ($this->handler)(new StartShiftChangeCommand(
            shiftId: $shiftId->asString(),
            organizationId: $organizationId->asString(),
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function keeps_the_shift_applying_when_only_a_trial_run_is_still_in_flight(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $shiftId = ShiftId::generate();

        $shift = $this->createMock(Shift::class);
        $shift->method('id')->willReturn($shiftId);
        $shift->method('organizationId')->willReturn($organizationId);
        $shift->method('changeCriteria')->willReturn(ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0'));
        $shift->expects($this->once())->method('startChange');
        $shift->expects($this->never())->method('complete');
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);
        $this->shifts->expects($this->once())->method('save')->with($shift);

        $this->shiftTargets->method('findByShiftIdAndStatuses')->willReturn([]);
        $this->shiftTargets->method('countByShiftIdAndStatuses')->willReturn(1);
        $this->bus->expects($this->never())->method('dispatch');

        // Act
        ($this->handler)(new StartShiftChangeCommand(
            shiftId: $shiftId->asString(),
            organizationId: $organizationId->asString(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftTargets = $this->createMock(ShiftTargetRepositoryInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->handler = new StartShiftChangeHandler($this->shifts, $this->shiftTargets, $this->bus, new ShiftJobPayloadFactory());
    }
}
