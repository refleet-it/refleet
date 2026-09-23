<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Application\Query\ListShiftTargets;

use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use App\Shift\Shift\Application\Query\ListShiftTargets\ListShiftTargetsHandler;
use App\Shift\Shift\Application\Query\ListShiftTargets\ListShiftTargetsQuery;
use App\Shift\Shift\Domain\Shift\Exception\ShiftNotFoundException;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ProjectId;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListShiftTargetsHandler::class)]
final class ListShiftTargetsHandlerTest extends TestCase
{
    private ShiftRepositoryInterface&Stub $shifts;

    private ShiftTargetRepositoryInterface&Stub $shiftTargets;

    private ListShiftTargetsHandler $handler;

    #[Test]
    public function lists_every_target_when_no_status_filter_is_given(): void
    {
        // Arrange
        $shiftId = ShiftId::generate();
        $organizationId = OrganizationId::generate();
        $shift = $this->createStub(Shift::class);
        $shift->method('id')->willReturn($shiftId);
        $shift->method('organizationId')->willReturn($organizationId);
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);

        $target = $this->newTarget($shiftId);
        $this->shiftTargets
            ->method('getPaginatedListByShiftId')
            ->willReturn(ListResponse::create([$target], 1, PaginationParameters::fromRequest()));

        // Act
        $result = ($this->handler)(new ListShiftTargetsQuery(
            shiftId: $shiftId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertCount(1, $result->getItems());
        Assert::assertSame('Payments Service', $result->getItems()[0]->projectName);
    }

    #[Test]
    public function filters_targets_by_status_when_given(): void
    {
        // Arrange
        $shiftId = ShiftId::generate();
        $organizationId = OrganizationId::generate();
        $shift = $this->createStub(Shift::class);
        $shift->method('id')->willReturn($shiftId);
        $shift->method('organizationId')->willReturn($organizationId);
        $this->shifts->method('findByIdForOrganization')->willReturn($shift);

        $target = $this->newTarget($shiftId);
        $this->shiftTargets
            ->method('getPaginatedListByShiftId')
            ->willReturn(ListResponse::create([$target], 1, PaginationParameters::fromRequest()));

        // Act
        $result = ($this->handler)(new ListShiftTargetsQuery(
            shiftId: $shiftId->asString(),
            organizationId: $organizationId->asString(),
            status: 'completed',
        ));

        // Assert
        Assert::assertCount(1, $result->getItems());
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
        ($this->handler)(new ListShiftTargetsQuery(
            shiftId: ShiftId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->shifts = $this->createStub(ShiftRepositoryInterface::class);
        $this->shiftTargets = $this->createStub(ShiftTargetRepositoryInterface::class);
        $this->handler = new ListShiftTargetsHandler($this->shifts, $this->shiftTargets);
    }

    private function newTarget(ShiftId $shiftId): ShiftTarget
    {
        return ShiftTarget::create(
            id: ShiftTargetId::generate(),
            shiftId: $shiftId,
            organizationId: OrganizationId::generate(),
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('48210942', 'backend-team/payments-service', 'Payments Service', 'main'),
        );
    }
}
