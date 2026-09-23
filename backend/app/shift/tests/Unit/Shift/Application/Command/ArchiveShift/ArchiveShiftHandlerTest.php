<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Application\Command\ArchiveShift;

use App\Shift\Shift\Application\Command\ArchiveShift\ArchiveShiftCommand;
use App\Shift\Shift\Application\Command\ArchiveShift\ArchiveShiftHandler;
use App\Shift\Shift\Domain\Shift\Exception\InvalidShiftStateTransitionException;
use App\Shift\Shift\Domain\Shift\Exception\ShiftNotFoundException;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\AccountId;
use App\Shift\Shift\Domain\Shift\ValueObject\ChangeCriteria;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArchiveShiftHandler::class)]
final class ArchiveShiftHandlerTest extends TestCase
{
    private ShiftRepositoryInterface&MockObject $shifts;

    private ArchiveShiftHandler $handler;

    #[Test]
    public function archives_the_shift(): void
    {
        // Arrange
        $shift = $this->startedShift();
        $shift->complete();
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);
        $this->shifts->expects($this->once())->method('save')->with($shift);

        // Act
        ($this->handler)(new ArchiveShiftCommand(
            shiftId: $shift->id()->asString(),
            organizationId: $shift->organizationId()->asString(),
        ));

        // Assert
        Assert::assertTrue($shift->isArchived());
    }

    #[Test]
    public function refuses_to_archive_a_shift_applying_its_change(): void
    {
        // Arrange
        $shift = $this->startedShift();
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);
        $this->shifts->expects($this->never())->method('save');

        // Assert
        $this->expectException(InvalidShiftStateTransitionException::class);

        // Act
        ($this->handler)(new ArchiveShiftCommand(
            shiftId: $shift->id()->asString(),
            organizationId: $shift->organizationId()->asString(),
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_not_found_when_the_shift_does_not_exist_or_belongs_to_another_organization(): void
    {
        // Arrange
        $this->shifts->method('findByIdForOrganization')->willReturn(null);
        $this->shifts->expects($this->never())->method('save');

        // Assert
        $this->expectException(ShiftNotFoundException::class);

        // Act
        ($this->handler)(new ArchiveShiftCommand(
            shiftId: ShiftId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->handler = new ArchiveShiftHandler($this->shifts);
    }

    private function startedShift(): Shift
    {
        $shift = Shift::draft(
            id: ShiftId::generate(),
            organizationId: OrganizationId::generate(),
            title: 'Test shift',
            description: null,
            createdBy: AccountId::generate(),
            qualificationId: null,
        );
        $shift->defineChange(ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0'));
        $shift->startChange();

        return $shift;
    }
}
