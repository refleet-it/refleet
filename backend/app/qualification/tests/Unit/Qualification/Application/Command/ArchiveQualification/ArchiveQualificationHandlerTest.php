<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Application\Command\ArchiveQualification;

use App\Qualification\Qualification\Application\Command\ArchiveQualification\ArchiveQualificationCommand;
use App\Qualification\Qualification\Application\Command\ArchiveQualification\ArchiveQualificationHandler;
use App\Qualification\Qualification\Domain\Qualification\Exception\InvalidQualificationStateTransitionException;
use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationNotFoundException;
use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\AccountId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationCriteria;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArchiveQualificationHandler::class)]
final class ArchiveQualificationHandlerTest extends TestCase
{
    private QualificationRepositoryInterface&MockObject $qualifications;

    private ArchiveQualificationHandler $handler;

    #[Test]
    public function archives_the_qualification(): void
    {
        // Arrange
        $qualification = $this->completedQualification();
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);
        $this->qualifications->expects($this->once())->method('save')->with($qualification);

        // Act
        ($this->handler)(new ArchiveQualificationCommand(
            qualificationId: $qualification->id()->asString(),
            organizationId: $qualification->organizationId()->asString(),
        ));

        // Assert
        Assert::assertTrue($qualification->isArchived());
    }

    #[Test]
    public function refuses_to_archive_a_running_qualification(): void
    {
        // Arrange
        $qualification = $this->completedQualification(complete: false);
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);
        $this->qualifications->expects($this->never())->method('save');

        // Assert
        $this->expectException(InvalidQualificationStateTransitionException::class);

        // Act
        ($this->handler)(new ArchiveQualificationCommand(
            qualificationId: $qualification->id()->asString(),
            organizationId: $qualification->organizationId()->asString(),
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_not_found_when_the_qualification_does_not_exist_or_belongs_to_another_organization(): void
    {
        // Arrange
        $this->qualifications->method('findByIdForOrganization')->willReturn(null);
        $this->qualifications->expects($this->never())->method('save');

        // Assert
        $this->expectException(QualificationNotFoundException::class);

        // Act
        ($this->handler)(new ArchiveQualificationCommand(
            qualificationId: QualificationId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->qualifications = $this->createMock(QualificationRepositoryInterface::class);
        $this->handler = new ArchiveQualificationHandler($this->qualifications);
    }

    private function completedQualification(bool $complete = true): Qualification
    {
        $qualification = Qualification::draft(
            id: QualificationId::generate(),
            organizationId: OrganizationId::generate(),
            title: 'Test qualification',
            description: null,
            createdBy: AccountId::generate(),
            criteria: QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?'),
        );
        $qualification->start();

        if ($complete) {
            $qualification->complete();
        }

        return $qualification;
    }
}
