<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Application\Command\OverrideQualificationTarget;

use App\Qualification\Qualification\Application\Command\OverrideQualificationTarget\OverrideQualificationTargetCommand;
use App\Qualification\Qualification\Application\Command\OverrideQualificationTarget\OverrideQualificationTargetHandler;
use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationArchivedException;
use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\AccountId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationCriteria;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Exception\QualificationTargetNotFoundException;
use App\Qualification\Qualification\Domain\QualificationTarget\Model\QualificationTarget;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\QualificationTargetId;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(OverrideQualificationTargetHandler::class)]
final class OverrideQualificationTargetHandlerTest extends TestCase
{
    private QualificationTargetRepositoryInterface&MockObject $qualificationTargets;

    private QualificationRepositoryInterface&MockObject $qualifications;

    private OverrideQualificationTargetHandler $handler;

    #[Test]
    public function refuses_to_override_a_target_of_an_archived_qualification(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualificationId = QualificationId::generate();

        $target = $this->createMock(QualificationTarget::class);
        $target->method('qualificationId')->willReturn($qualificationId);
        $target->method('organizationId')->willReturn($organizationId);
        $target->expects($this->never())->method('override');
        $this->qualificationTargets->method('findById')->willReturn($target);
        $this->qualificationTargets->expects($this->never())->method('save');

        $qualification = $this->liveQualification($qualificationId, $organizationId);
        $qualification->archive(new \DateTimeImmutable());
        $this->qualifications->expects($this->once())->method('findById')->willReturn($qualification);

        // Assert
        $this->expectException(QualificationArchivedException::class);

        // Act
        ($this->handler)(new OverrideQualificationTargetCommand(
            qualificationId: $qualificationId->asString(),
            organizationId: $organizationId->asString(),
            targetId: QualificationTargetId::generate()->asString(),
            qualified: true,
        ));
    }

    #[Test]
    public function overrides_the_target_belonging_to_the_given_qualification_and_organization(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualificationId = QualificationId::generate();
        $targetId = QualificationTargetId::generate();

        $target = $this->createMock(QualificationTarget::class);
        $target->method('qualificationId')->willReturn($qualificationId);
        $target->method('organizationId')->willReturn($organizationId);
        $target->expects($this->once())->method('override')->with(true, 'Confirmed manually');
        $this->qualificationTargets->method('findById')->willReturn($target);
        $this->qualificationTargets->expects($this->once())->method('save')->with($target);
        $this->qualifications->expects($this->once())->method('findById')->with($qualificationId)->willReturn($this->liveQualification($qualificationId, $organizationId));

        // Act
        ($this->handler)(new OverrideQualificationTargetCommand(
            qualificationId: $qualificationId->asString(),
            organizationId: $organizationId->asString(),
            targetId: $targetId->asString(),
            qualified: true,
            note: 'Confirmed manually',
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_not_found_when_the_target_does_not_exist(): void
    {
        // Arrange
        $this->qualificationTargets->method('findById')->willReturn(null);

        // Assert
        $this->expectException(QualificationTargetNotFoundException::class);

        // Act
        ($this->handler)(new OverrideQualificationTargetCommand(
            qualificationId: QualificationId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
            targetId: QualificationTargetId::generate()->asString(),
            qualified: true,
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_not_found_when_the_target_belongs_to_another_qualification(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();

        $target = $this->createStub(QualificationTarget::class);
        $target->method('qualificationId')->willReturn(QualificationId::generate());
        $target->method('organizationId')->willReturn($organizationId);
        $this->qualificationTargets->method('findById')->willReturn($target);

        // Assert
        $this->expectException(QualificationTargetNotFoundException::class);

        // Act
        ($this->handler)(new OverrideQualificationTargetCommand(
            qualificationId: QualificationId::generate()->asString(),
            organizationId: $organizationId->asString(),
            targetId: QualificationTargetId::generate()->asString(),
            qualified: true,
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_not_found_when_the_target_belongs_to_another_organization(): void
    {
        // Arrange
        $qualificationId = QualificationId::generate();

        $target = $this->createStub(QualificationTarget::class);
        $target->method('qualificationId')->willReturn($qualificationId);
        $target->method('organizationId')->willReturn(OrganizationId::generate());
        $this->qualificationTargets->method('findById')->willReturn($target);

        // Assert
        $this->expectException(QualificationTargetNotFoundException::class);

        // Act
        ($this->handler)(new OverrideQualificationTargetCommand(
            qualificationId: $qualificationId->asString(),
            organizationId: OrganizationId::generate()->asString(),
            targetId: QualificationTargetId::generate()->asString(),
            qualified: true,
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->qualificationTargets = $this->createMock(QualificationTargetRepositoryInterface::class);
        $this->qualifications = $this->createMock(QualificationRepositoryInterface::class);
        $this->handler = new OverrideQualificationTargetHandler($this->qualificationTargets, $this->qualifications);
    }

    private function liveQualification(QualificationId $qualificationId, OrganizationId $organizationId): Qualification
    {
        return Qualification::draft(
            id: $qualificationId,
            organizationId: $organizationId,
            title: 'Test qualification',
            description: null,
            createdBy: AccountId::generate(),
            criteria: QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?'),
        );
    }
}
