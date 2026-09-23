<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Application\Query\ListShifts;

use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shift\Shift\Application\Query\ListShifts\ListShiftsHandler;
use App\Shift\Shift\Application\Query\ListShifts\ListShiftsQuery;
use App\Shift\Shift\Domain\Shift\Enum\ShiftStatusEnum;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\ChangeCriteria;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListShiftsHandler::class)]
final class ListShiftsHandlerTest extends TestCase
{
    private ShiftRepositoryInterface&Stub $shifts;

    private ShiftTargetRepositoryInterface&Stub $shiftTargets;

    private ListShiftsHandler $handler;

    #[Test]
    public function lists_shifts_with_a_progress_percentage_derived_from_the_status_breakdown(): void
    {
        // Arrange
        $shift = $this->createStub(Shift::class);
        $shift->method('id')->willReturn(ShiftId::generate());
        $shift->method('title')->willReturn('Bump acme/legacy-lib to v3');
        $shift->method('description')->willReturn(null);
        $shift->method('status')->willReturn(ShiftStatusEnum::APPLYING_CHANGE);
        $shift->method('qualificationId')->willReturn(null);
        $shift->method('changeCriteria')->willReturn(ChangeCriteria::ai('Bump acme/legacy-lib to ^3.0'));
        $shift->method('createdAt')->willReturn(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));

        $this->shifts
            ->method('getPaginatedList')
            ->willReturn(ListResponse::create(
                items: [$shift],
                totalItems: 1,
                pagination: PaginationParameters::fromRequest(),
            ));
        $this->shiftTargets->method('statusBreakdown')->willReturn([
            ShiftTargetStatusEnum::CHANGE_IN_PROGRESS->value => 1,
            ShiftTargetStatusEnum::COMPLETED->value => 3,
        ]);

        // Act
        $result = ($this->handler)(new ListShiftsQuery(OrganizationId::generate()->asString()));

        // Assert
        Assert::assertCount(1, $result->getItems());
        Assert::assertSame(4, $result->getItems()[0]->targetCount);
        Assert::assertSame(75, $result->getItems()[0]->progressPercent);
        Assert::assertSame('ai', $result->getItems()[0]->changeMode);
        Assert::assertNull($result->getItems()[0]->qualificationId);
    }

    #[Test]
    public function counts_only_terminal_statuses_towards_the_terminal_target_count(): void
    {
        // Arrange
        $shift = $this->createStub(Shift::class);
        $shift->method('id')->willReturn(ShiftId::generate());
        $shift->method('title')->willReturn('Bump acme/legacy-lib to v3');
        $shift->method('description')->willReturn(null);
        $shift->method('status')->willReturn(ShiftStatusEnum::APPLYING_CHANGE);
        $shift->method('qualificationId')->willReturn(null);
        $shift->method('changeCriteria')->willReturn(null);
        $shift->method('createdAt')->willReturn(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));

        $this->shifts
            ->method('getPaginatedList')
            ->willReturn(ListResponse::create(
                items: [$shift],
                totalItems: 1,
                pagination: PaginationParameters::fromRequest(),
            ));
        $this->shiftTargets->method('statusBreakdown')->willReturn([
            ShiftTargetStatusEnum::COMPLETED->value => 2,
            ShiftTargetStatusEnum::CHANGE_FAILED->value => 1,
            ShiftTargetStatusEnum::MERGE_REQUEST_OPEN->value => 1,
        ]);

        // Act
        $result = ($this->handler)(new ListShiftsQuery(OrganizationId::generate()->asString()));

        // Assert
        Assert::assertSame(4, $result->getItems()[0]->targetCount);
        Assert::assertSame(3, $result->getItems()[0]->terminalTargetCount);
    }

    #[Test]
    public function reports_no_progress_at_all_when_a_shift_has_no_targets(): void
    {
        // Arrange
        $shift = $this->createStub(Shift::class);
        $shift->method('id')->willReturn(ShiftId::generate());
        $shift->method('title')->willReturn('Empty shift');
        $shift->method('description')->willReturn(null);
        $shift->method('status')->willReturn(ShiftStatusEnum::DRAFT);
        $shift->method('qualificationId')->willReturn(null);
        $shift->method('changeCriteria')->willReturn(null);
        $shift->method('createdAt')->willReturn(new \DateTimeImmutable());

        $this->shifts
            ->method('getPaginatedList')
            ->willReturn(ListResponse::create(
                items: [$shift],
                totalItems: 1,
                pagination: PaginationParameters::fromRequest(),
            ));
        $this->shiftTargets->method('statusBreakdown')->willReturn([]);

        // Act
        $result = ($this->handler)(new ListShiftsQuery(OrganizationId::generate()->asString()));

        // Assert
        Assert::assertSame(0, $result->getItems()[0]->targetCount);
        Assert::assertSame(0, $result->getItems()[0]->terminalTargetCount);
        Assert::assertNull($result->getItems()[0]->progressPercent);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->shifts = $this->createStub(ShiftRepositoryInterface::class);
        $this->shiftTargets = $this->createStub(ShiftTargetRepositoryInterface::class);
        $this->handler = new ListShiftsHandler($this->shifts, $this->shiftTargets);
    }
}
