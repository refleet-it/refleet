<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Shift\Application\Command\CreateShift;

use App\Shared\Domain\Service\ProjectCatalogInterface;
use App\Shared\Domain\Service\QualifiedProjectsInterface;
use App\Shared\Domain\ValueObject\ProjectCatalogEntry;
use App\Shift\Shift\Application\Command\CreateShift\CreateShiftCommand;
use App\Shift\Shift\Application\Command\CreateShift\CreateShiftHandler;
use App\Shift\Shift\Domain\Shift\Exception\ExplicitProjectSelectionRequiredException;
use App\Shift\Shift\Domain\Shift\Exception\NoTargetProjectsResolvedException;
use App\Shift\Shift\Domain\Shift\Model\Shift;
use App\Shift\Shift\Domain\Shift\Repository\ShiftRepositoryInterface;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\Repository\ShiftTargetRepositoryInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateShiftHandler::class)]
final class CreateShiftHandlerTest extends TestCase
{
    private ShiftRepositoryInterface&MockObject $shifts;

    private ShiftTargetRepositoryInterface&MockObject $shiftTargets;

    private ProjectCatalogInterface&MockObject $projectCatalog;

    private QualifiedProjectsInterface&MockObject $qualifiedProjects;

    private CreateShiftHandler $handler;

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function mode_1_pulls_every_currently_qualified_target_when_no_project_ids_are_given(): void
    {
        // Arrange
        $target = $this->qualificationTarget('11111111-1111-1111-1111-111111111111');

        $this->qualifiedProjects
            ->expects($this->once())
            ->method('forQualification')
            ->willReturnCallback(static function (string $organizationId, string $qualificationId, ?array $projectIds) use ($target): array {
                Assert::assertNull($projectIds);

                return [$target];
            });

        $savedTargets = null;
        $this->shiftTargets
            ->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(static function (array $targets) use (&$savedTargets): bool {
                $savedTargets = $targets;

                return true;
            }));

        $savedShift = null;
        $this->shifts
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Shift $shift) use (&$savedShift): bool {
                $savedShift = $shift;

                return true;
            }));

        // Act
        $result = ($this->handler)(new CreateShiftCommand(
            organizationId: '22222222-2222-2222-2222-222222222222',
            title: 'Shift from qualification',
            description: null,
            createdByAccountId: '33333333-3333-3333-3333-333333333333',
            qualificationId: '44444444-4444-4444-4444-444444444444',
            projectIds: null,
        ));

        // Assert
        Assert::assertCount(1, $savedTargets);
        Assert::assertInstanceOf(ShiftTarget::class, $savedTargets[0]);
        Assert::assertSame('44444444-4444-4444-4444-444444444444', $savedShift->qualificationId()?->asString());
        Assert::assertSame(1, $result->targetCount);
        Assert::assertSame('44444444-4444-4444-4444-444444444444', $result->qualificationId);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function mode_2_pulls_the_explicit_targets_of_a_qualification_regardless_of_status(): void
    {
        // Arrange
        $target = $this->qualificationTarget('11111111-1111-1111-1111-111111111111');

        $this->qualifiedProjects
            ->expects($this->once())
            ->method('forQualification')
            ->willReturnCallback(static function (string $organizationId, string $qualificationId, ?array $projectIds) use ($target): array {
                Assert::assertSame(['11111111-1111-1111-1111-111111111111'], $projectIds);

                return [$target];
            });

        $this->shifts->expects($this->once())->method('save');
        $this->shiftTargets->expects($this->once())->method('saveAll');

        // Act
        $result = ($this->handler)(new CreateShiftCommand(
            organizationId: '22222222-2222-2222-2222-222222222222',
            title: 'Shift with manual subset',
            description: null,
            createdByAccountId: '33333333-3333-3333-3333-333333333333',
            qualificationId: '44444444-4444-4444-4444-444444444444',
            projectIds: ['11111111-1111-1111-1111-111111111111'],
        ));

        // Assert
        Assert::assertSame(1, $result->targetCount);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function mode_3_pulls_projects_by_id_when_no_qualification_is_given(): void
    {
        // Arrange
        $project = $this->projectEntry('11111111-1111-1111-1111-111111111111');

        $this->projectCatalog
            ->expects($this->once())
            ->method('byIds')
            ->willReturn([$project]);

        $savedShift = null;
        $this->shifts
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Shift $shift) use (&$savedShift): bool {
                $savedShift = $shift;

                return true;
            }));
        $this->shiftTargets->expects($this->once())->method('saveAll');

        // Act
        $result = ($this->handler)(new CreateShiftCommand(
            organizationId: '22222222-2222-2222-2222-222222222222',
            title: 'Fully manual shift',
            description: null,
            createdByAccountId: '33333333-3333-3333-3333-333333333333',
            qualificationId: null,
            projectIds: ['11111111-1111-1111-1111-111111111111'],
        ));

        // Assert
        Assert::assertNull($savedShift->qualificationId());
        Assert::assertNull($result->qualificationId);
        Assert::assertSame(1, $result->targetCount);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function mode_3_without_project_ids_is_rejected_before_looking_up_projects(): void
    {
        // Arrange
        $this->projectCatalog->expects($this->never())->method('byIds');
        $this->shifts->expects($this->never())->method('save');

        // Assert
        $this->expectException(ExplicitProjectSelectionRequiredException::class);

        // Act
        ($this->handler)(new CreateShiftCommand(
            organizationId: '22222222-2222-2222-2222-222222222222',
            title: 'Fully manual shift',
            description: null,
            createdByAccountId: '33333333-3333-3333-3333-333333333333',
            qualificationId: null,
            projectIds: null,
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function mode_3_with_an_empty_project_ids_array_is_also_rejected(): void
    {
        $this->projectCatalog->expects($this->never())->method('byIds');

        $this->expectException(ExplicitProjectSelectionRequiredException::class);

        ($this->handler)(new CreateShiftCommand(
            organizationId: '22222222-2222-2222-2222-222222222222',
            title: 'Fully manual shift',
            description: null,
            createdByAccountId: '33333333-3333-3333-3333-333333333333',
            qualificationId: null,
            projectIds: [],
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_when_the_resolved_target_list_is_empty_in_qualification_mode(): void
    {
        // Arrange
        $this->qualifiedProjects->method('forQualification')->willReturn([]);
        $this->shifts->expects($this->never())->method('save');

        // Assert
        $this->expectException(NoTargetProjectsResolvedException::class);

        // Act
        ($this->handler)(new CreateShiftCommand(
            organizationId: '22222222-2222-2222-2222-222222222222',
            title: 'Empty shift',
            description: null,
            createdByAccountId: '33333333-3333-3333-3333-333333333333',
            qualificationId: '44444444-4444-4444-4444-444444444444',
            projectIds: null,
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_when_the_resolved_target_list_is_empty_in_manual_mode(): void
    {
        // Arrange
        $this->projectCatalog->method('byIds')->willReturn([]);
        $this->shifts->expects($this->never())->method('save');

        // Assert
        $this->expectException(NoTargetProjectsResolvedException::class);

        // Act
        ($this->handler)(new CreateShiftCommand(
            organizationId: '22222222-2222-2222-2222-222222222222',
            title: 'Empty shift',
            description: null,
            createdByAccountId: '33333333-3333-3333-3333-333333333333',
            qualificationId: null,
            projectIds: ['11111111-1111-1111-1111-111111111111'],
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function maps_the_project_snapshot_fields_onto_the_created_shift_target(): void
    {
        // Arrange
        $target = $this->projectEntry('11111111-1111-1111-1111-111111111111');

        $this->qualifiedProjects->method('forQualification')->willReturn([$target]);

        $savedTargets = null;
        $this->shiftTargets
            ->method('saveAll')
            ->with($this->callback(static function (array $targets) use (&$savedTargets): bool {
                $savedTargets = $targets;

                return true;
            }));

        // Act
        ($this->handler)(new CreateShiftCommand(
            organizationId: '22222222-2222-2222-2222-222222222222',
            title: 'Shift',
            description: null,
            createdByAccountId: '33333333-3333-3333-3333-333333333333',
            qualificationId: '44444444-4444-4444-4444-444444444444',
            projectIds: null,
        ));

        // Assert
        $snapshot = $savedTargets[0]->projectSnapshot();
        Assert::assertSame('48210942', $snapshot->externalId());
        Assert::assertSame('backend-team/payments-service', $snapshot->path());
        Assert::assertSame('Payments Service', $snapshot->name());
        Assert::assertSame('main', $snapshot->defaultBranch());
        Assert::assertTrue($savedTargets[0]->projectId()->equals(\App\Shift\Shift\Domain\ShiftTarget\ValueObject\ProjectId::fromString('11111111-1111-1111-1111-111111111111')));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->shifts = $this->createMock(ShiftRepositoryInterface::class);
        $this->shiftTargets = $this->createMock(ShiftTargetRepositoryInterface::class);
        $this->projectCatalog = $this->createMock(ProjectCatalogInterface::class);
        $this->qualifiedProjects = $this->createMock(QualifiedProjectsInterface::class);
        $this->handler = new CreateShiftHandler($this->shifts, $this->shiftTargets, $this->projectCatalog, $this->qualifiedProjects);
    }

    private function qualificationTarget(string $projectId): ProjectCatalogEntry
    {
        return $this->projectEntry($projectId);
    }

    private function projectEntry(string $projectId): ProjectCatalogEntry
    {
        return new ProjectCatalogEntry(
            id: $projectId,
            externalId: '48210942',
            name: 'Payments Service',
            path: 'backend-team/payments-service',
            defaultBranch: 'main',
        );
    }
}
