<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Application\Query\GetQualificationTarget;

use App\Qualification\Qualification\Application\Query\GetQualificationTarget\GetQualificationTargetHandler;
use App\Qualification\Qualification\Application\Query\GetQualificationTarget\GetQualificationTargetQuery;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Exception\QualificationTargetNotFoundException;
use App\Qualification\Qualification\Domain\QualificationTarget\Model\QualificationTarget;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\ProjectId;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\QualificationTargetId;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetQualificationTargetHandler::class)]
final class GetQualificationTargetHandlerTest extends TestCase
{
    private QualificationTargetRepositoryInterface&Stub $qualificationTargets;

    private GetQualificationTargetHandler $handler;

    #[Test]
    public function returns_the_detail_of_an_owned_target(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualificationId = QualificationId::generate();

        $target = QualificationTarget::create(
            id: QualificationTargetId::generate(),
            qualificationId: $qualificationId,
            organizationId: $organizationId,
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('48210942', 'backend-team/payments-service', 'Payments Service', 'main'),
        );

        $this->qualificationTargets->method('findById')->willReturn($target);

        // Act
        $result = ($this->handler)(new GetQualificationTargetQuery(
            targetId: $target->id()->asString(),
            qualificationId: $qualificationId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertSame($target->id()->asString(), $result->id);
        Assert::assertSame('Payments Service', $result->projectSnapshotName);
        Assert::assertSame('pending', $result->status);
    }

    #[Test]
    public function throws_not_found_when_the_target_does_not_exist(): void
    {
        // Arrange
        $this->qualificationTargets->method('findById')->willReturn(null);

        // Assert
        $this->expectException(QualificationTargetNotFoundException::class);

        // Act
        ($this->handler)(new GetQualificationTargetQuery(
            targetId: QualificationTargetId::generate()->asString(),
            qualificationId: QualificationId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[Test]
    public function throws_not_found_when_the_target_belongs_to_another_qualification(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $target = QualificationTarget::create(
            id: QualificationTargetId::generate(),
            qualificationId: QualificationId::generate(),
            organizationId: $organizationId,
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('1', 'group/project', 'Project', 'main'),
        );
        $this->qualificationTargets->method('findById')->willReturn($target);

        // Assert
        $this->expectException(QualificationTargetNotFoundException::class);

        // Act
        ($this->handler)(new GetQualificationTargetQuery(
            targetId: $target->id()->asString(),
            qualificationId: QualificationId::generate()->asString(),
            organizationId: $organizationId->asString(),
        ));
    }

    #[Test]
    public function throws_not_found_when_the_target_belongs_to_another_organization(): void
    {
        // Arrange
        $qualificationId = QualificationId::generate();

        $target = QualificationTarget::create(
            id: QualificationTargetId::generate(),
            qualificationId: $qualificationId,
            organizationId: OrganizationId::generate(),
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('1', 'group/project', 'Project', 'main'),
        );
        $this->qualificationTargets->method('findById')->willReturn($target);

        // Assert
        $this->expectException(QualificationTargetNotFoundException::class);

        // Act
        ($this->handler)(new GetQualificationTargetQuery(
            targetId: $target->id()->asString(),
            qualificationId: $qualificationId->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->qualificationTargets = $this->createStub(QualificationTargetRepositoryInterface::class);
        $this->handler = new GetQualificationTargetHandler($this->qualificationTargets);
    }
}
