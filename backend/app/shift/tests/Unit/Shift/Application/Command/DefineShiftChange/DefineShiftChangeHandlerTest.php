<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Application\Command\DefineShiftChange;

use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shift\Shift\Application\Command\DefineShiftChange\DefineShiftChangeCommand;
use App\Shift\Shift\Application\Command\DefineShiftChange\DefineShiftChangeHandler;
use App\Shift\Shift\Domain\Shift\Exception\ShiftNotFoundException;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\ChangeCriteria;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefineShiftChangeHandler::class)]
final class DefineShiftChangeHandlerTest extends TestCase
{
    private ShiftRepositoryInterface&MockObject $shifts;

    private DefineShiftChangeHandler $handler;

    #[Test]
    public function defines_change_criteria_on_the_owned_shift(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $shiftId = ShiftId::generate();

        $shift = $this->createMock(Shift::class);
        $shift->method('organizationId')->willReturn($organizationId);
        $shift
            ->expects($this->once())
            ->method('defineChange')
            ->with($this->callback(static function (ChangeCriteria $criteria): bool {
                Assert::assertSame('Bump acme/legacy-lib to ^3.0', $criteria->prompt());
                Assert::assertSame(CriteriaEngineEnum::KIRO, $criteria->engine());

                return true;
            }));

        $this->shifts->method('findByIdForOrganization')->willReturn($shift);
        $this->shifts->expects($this->once())->method('save')->with($shift);

        // Act
        ($this->handler)(new DefineShiftChangeCommand(
            shiftId: $shiftId->asString(),
            organizationId: $organizationId->asString(),
            changeMode: 'ai',
            changeEngine: 'kiro',
            changePrompt: 'Bump acme/legacy-lib to ^3.0',
        ));
    }

    #[Test]
    public function defines_ai_change_criteria_with_a_model_override(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $shiftId = ShiftId::generate();

        $shift = $this->createMock(Shift::class);
        $shift->method('organizationId')->willReturn($organizationId);
        $shift
            ->expects($this->once())
            ->method('defineChange')
            ->with($this->callback(static function (ChangeCriteria $criteria): bool {
                Assert::assertSame('Apply the migration', $criteria->prompt());
                Assert::assertSame('claude-haiku-4-5-20251001', $criteria->model());

                return true;
            }));

        $this->shifts->method('findByIdForOrganization')->willReturn($shift);
        $this->shifts->expects($this->once())->method('save')->with($shift);

        // Act
        ($this->handler)(new DefineShiftChangeCommand(
            shiftId: $shiftId->asString(),
            organizationId: $organizationId->asString(),
            changeMode: 'ai',
            changePrompt: 'Apply the migration',
            changeModel: 'claude-haiku-4-5-20251001',
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_not_found_when_the_shift_does_not_exist_or_belongs_to_another_organization(): void
    {
        // Arrange: the repository scopes the lookup by organization at the query level, so
        // "doesn't exist" and "belongs to someone else" both surface as null here.
        $this->shifts->method('findByIdForOrganization')->willReturn(null);

        // Assert
        $this->expectException(ShiftNotFoundException::class);

        // Act
        ($this->handler)(new DefineShiftChangeCommand(
            shiftId: ShiftId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
            changeMode: 'ai',
            changePrompt: 'Apply the migration',
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->handler = new DefineShiftChangeHandler($this->shifts);
    }
}
