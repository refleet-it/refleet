<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Application\Command\CancelShift;

use App\Shift\Shift\Application\Command\CancelShift\CancelShiftCommand;
use App\Shift\Shift\Application\Command\CancelShift\CancelShiftHandler;
use App\Shift\Shift\Domain\Shift\Exception\ShiftNotFoundException;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Infrastructure\Bus\OwnerJobsCancellationRequested\OwnerJobsCancellationRequestedMessage;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(CancelShiftHandler::class)]
final class CancelShiftHandlerTest extends TestCase
{
    private ShiftRepositoryInterface&MockObject $shifts;

    private ShiftTargetRepositoryInterface&MockObject $shiftTargets;

    private MessageBusInterface&MockObject $bus;

    private CancelShiftHandler $handler;

    #[Test]
    public function cancels_the_shift_its_non_terminal_targets_and_the_in_flight_runner_jobs(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $shiftId = ShiftId::generate();

        $shift = $this->createMock(Shift::class);
        $shift->method('id')->willReturn($shiftId);
        $shift->method('organizationId')->willReturn($organizationId);
        $shift->expects($this->once())->method('cancel')->with('Superseded by a newer shift');
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);
        $this->shifts->expects($this->once())->method('save')->with($shift);

        $target = $this->createMock(ShiftTarget::class);
        $target->expects($this->once())->method('cancel');
        $this->shiftTargets->method('findNonTerminalByShiftId')->willReturn([$target]);
        $this->shiftTargets->expects($this->once())->method('saveAll')->with([$target]);

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (OwnerJobsCancellationRequestedMessage $command) use ($shiftId, $organizationId): Envelope {
                Assert::assertSame($shiftId->asString(), $command->ownerId);
                Assert::assertSame($organizationId->asString(), $command->organizationId);

                return new Envelope(new \stdClass());
            });

        // Act
        ($this->handler)(new CancelShiftCommand(
            shiftId: $shiftId->asString(),
            organizationId: $organizationId->asString(),
            reason: 'Superseded by a newer shift',
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_not_found_before_cancelling_anything_when_the_shift_does_not_exist_or_belongs_to_another_organization(): void
    {
        // Arrange: the repository scopes the lookup by organization at the query level, so
        // "doesn't exist" and "belongs to someone else" both surface as null here.
        $this->shifts->method('findByIdForOrganization')->willReturn(null);
        $this->shiftTargets->expects($this->never())->method('findNonTerminalByShiftId');
        $this->bus->expects($this->never())->method('dispatch');

        // Assert
        $this->expectException(ShiftNotFoundException::class);

        // Act
        ($this->handler)(new CancelShiftCommand(
            shiftId: ShiftId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftTargets = $this->createMock(ShiftTargetRepositoryInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->handler = new CancelShiftHandler($this->shifts, $this->shiftTargets, $this->bus);
    }
}
