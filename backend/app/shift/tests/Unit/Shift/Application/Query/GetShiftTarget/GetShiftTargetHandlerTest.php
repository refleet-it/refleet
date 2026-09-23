<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Application\Query\GetShiftTarget;

use App\Shared\Domain\ValueObject\ProjectSnapshot;
use App\Shift\Shift\Application\Query\GetShiftTarget\GetShiftTargetHandler;
use App\Shift\Shift\Application\Query\GetShiftTarget\GetShiftTargetQuery;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Exception\ShiftTargetNotFoundException;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ProjectId;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetShiftTargetHandler::class)]
final class GetShiftTargetHandlerTest extends TestCase
{
    private ShiftTargetRepositoryInterface&Stub $shiftTargets;

    private GetShiftTargetHandler $handler;

    #[Test]
    public function returns_the_shift_target_detail(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $shiftId = ShiftId::generate();
        $targetId = ShiftTargetId::generate();

        $target = ShiftTarget::create(
            id: $targetId,
            shiftId: $shiftId,
            organizationId: $organizationId,
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('48210942', 'backend-team/payments-service', 'Payments Service', 'main'),
        );
        $this->shiftTargets->method('findById')->willReturn($target);

        // Act
        $detail = ($this->handler)(new GetShiftTargetQuery(
            shiftTargetId: $targetId->asString(),
            shiftId: $shiftId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertSame($targetId->asString(), $detail->id);
        Assert::assertSame('Payments Service', $detail->projectSnapshotName);
        Assert::assertSame('pending_change', $detail->status);
        Assert::assertSame('none', $detail->mergeRequestStatus);
    }

    #[Test]
    public function throws_not_found_when_the_target_belongs_to_another_organization(): void
    {
        // Arrange
        $shiftId = ShiftId::generate();
        $target = ShiftTarget::create(
            id: ShiftTargetId::generate(),
            shiftId: $shiftId,
            organizationId: OrganizationId::generate(),
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('1', 'group/project', 'Project', 'main'),
        );
        $this->shiftTargets->method('findById')->willReturn($target);

        // Assert
        $this->expectException(ShiftTargetNotFoundException::class);

        // Act
        ($this->handler)(new GetShiftTargetQuery(
            shiftTargetId: ShiftTargetId::generate()->asString(),
            shiftId: $shiftId->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[Test]
    public function throws_not_found_when_the_target_belongs_to_another_shift(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $target = ShiftTarget::create(
            id: ShiftTargetId::generate(),
            shiftId: ShiftId::generate(),
            organizationId: $organizationId,
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('1', 'group/project', 'Project', 'main'),
        );
        $this->shiftTargets->method('findById')->willReturn($target);

        // Assert
        $this->expectException(ShiftTargetNotFoundException::class);

        // Act
        ($this->handler)(new GetShiftTargetQuery(
            shiftTargetId: ShiftTargetId::generate()->asString(),
            shiftId: ShiftId::generate()->asString(),
            organizationId: $organizationId->asString(),
        ));
    }

    #[Test]
    public function throws_not_found_when_the_target_does_not_exist(): void
    {
        // Arrange
        $this->shiftTargets->method('findById')->willReturn(null);

        // Assert
        $this->expectException(ShiftTargetNotFoundException::class);

        // Act
        ($this->handler)(new GetShiftTargetQuery(
            shiftTargetId: ShiftTargetId::generate()->asString(),
            shiftId: ShiftId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->shiftTargets = $this->createStub(ShiftTargetRepositoryInterface::class);
        $this->handler = new GetShiftTargetHandler($this->shiftTargets);
    }
}
