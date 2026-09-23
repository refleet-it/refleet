<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Application\Query\FindShiftTargetByMergeRequestUrl;

use App\Shift\Shift\Application\Query\FindShiftTargetByMergeRequestUrl\FindShiftTargetByMergeRequestUrlHandler;
use App\Shift\Shift\Application\Query\FindShiftTargetByMergeRequestUrl\FindShiftTargetByMergeRequestUrlQuery;
use App\Shift\Shift\Domain\Shift\ValueObject\OrganizationId;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(FindShiftTargetByMergeRequestUrlHandler::class)]
final class FindShiftTargetByMergeRequestUrlHandlerTest extends TestCase
{
    private ShiftTargetRepositoryInterface&Stub $shiftTargets;

    private FindShiftTargetByMergeRequestUrlHandler $handler;

    #[Test]
    public function returns_the_target_id_when_the_url_matches_a_target_in_the_same_organization(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $targetId = ShiftTargetId::generate();

        $target = $this->createStub(ShiftTarget::class);
        $target->method('organizationId')->willReturn($organizationId);
        $target->method('id')->willReturn($targetId);
        $this->shiftTargets->method('findByMergeRequestUrl')->willReturn($target);

        // Act
        $result = ($this->handler)(new FindShiftTargetByMergeRequestUrlQuery(
            organizationId: $organizationId->asString(),
            mergeRequestUrl: 'https://gitlab.example/mr/1',
        ));

        // Assert
        Assert::assertSame($targetId->asString(), $result);
    }

    #[Test]
    public function returns_null_when_no_target_has_that_merge_request_url(): void
    {
        // Arrange
        $this->shiftTargets->method('findByMergeRequestUrl')->willReturn(null);

        // Act
        $result = ($this->handler)(new FindShiftTargetByMergeRequestUrlQuery(
            organizationId: OrganizationId::generate()->asString(),
            mergeRequestUrl: 'https://gitlab.example/mr/1',
        ));

        // Assert
        Assert::assertNull($result);
    }

    #[Test]
    public function returns_null_when_the_matching_target_belongs_to_another_organization(): void
    {
        // Arrange
        $target = $this->createStub(ShiftTarget::class);
        $target->method('organizationId')->willReturn(OrganizationId::generate());
        $this->shiftTargets->method('findByMergeRequestUrl')->willReturn($target);

        // Act
        $result = ($this->handler)(new FindShiftTargetByMergeRequestUrlQuery(
            organizationId: OrganizationId::generate()->asString(),
            mergeRequestUrl: 'https://gitlab.example/mr/1',
        ));

        // Assert
        Assert::assertNull($result);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->shiftTargets = $this->createStub(ShiftTargetRepositoryInterface::class);
        $this->handler = new FindShiftTargetByMergeRequestUrlHandler($this->shiftTargets);
    }
}
