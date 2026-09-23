<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Application\Query\GetShift;

use App\Shift\Shift\Application\Query\GetShift\GetShiftHandler;
use App\Shift\Shift\Application\Query\GetShift\GetShiftQuery;
use App\Shift\Shift\Domain\Shift\Exception\ShiftNotFoundException;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\AccountId;
use App\Shift\Shift\Domain\Shift\ValueObject\ChangeCriteria;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetShiftHandler::class)]
final class GetShiftHandlerTest extends TestCase
{
    private ShiftRepositoryInterface&Stub $shifts;

    private ShiftTargetRepositoryInterface&Stub $shiftTargets;

    private GetShiftHandler $handler;

    #[Test]
    public function returns_the_shift_detail_with_status_breakdown_and_progress(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $shiftId = ShiftId::generate();

        $shift = Shift::draft(
            id: $shiftId,
            organizationId: $organizationId,
            title: 'Bump acme/legacy-lib',
            description: 'Removes the deprecated dependency',
            createdBy: AccountId::generate(),
            qualificationId: null,
        );
        $shift->defineChange(ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0'));
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);

        $this->shiftTargets->method('statusBreakdown')->willReturn([
            ShiftTargetStatusEnum::CHANGE_IN_PROGRESS->value => 1,
            ShiftTargetStatusEnum::COMPLETED->value => 1,
        ]);

        // Act
        $detail = ($this->handler)(new GetShiftQuery(
            shiftId: $shiftId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertSame($shiftId->asString(), $detail->id);
        Assert::assertSame('Bump acme/legacy-lib', $detail->title);
        Assert::assertSame('ai', $detail->changeMode);
        Assert::assertSame('Bump acme/legacy-lib to ^3.0', $detail->changePrompt);
        Assert::assertSame(2, $detail->targetCount);
        Assert::assertSame(50, $detail->progressPercent);
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
        ($this->handler)(new GetShiftQuery(
            shiftId: ShiftId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->shifts = $this->createStub(ShiftRepositoryInterface::class);
        $this->shiftTargets = $this->createStub(ShiftTargetRepositoryInterface::class);
        $this->handler = new GetShiftHandler($this->shifts, $this->shiftTargets);
    }
}
