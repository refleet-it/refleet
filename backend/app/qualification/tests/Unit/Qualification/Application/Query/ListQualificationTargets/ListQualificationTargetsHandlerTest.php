<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Application\Query\ListQualificationTargets;

use App\Qualification\Qualification\Application\Query\ListQualificationTargets\ListQualificationTargetsHandler;
use App\Qualification\Qualification\Application\Query\ListQualificationTargets\ListQualificationTargetsQuery;
use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationNotFoundException;
use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\AccountId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationCriteria;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationScore;
use App\Qualification\Qualification\Domain\QualificationTarget\Enum\QualificationTargetStatusEnum;
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

#[CoversClass(ListQualificationTargetsHandler::class)]
final class ListQualificationTargetsHandlerTest extends TestCase
{
    private QualificationRepositoryInterface&Stub $qualifications;

    private QualificationTargetRepositoryInterface&Stub $qualificationTargets;

    private ListQualificationTargetsHandler $handler;

    private QualificationId $qualificationId;

    private OrganizationId $organizationId;

    #[Test]
    public function returns_every_target_when_no_filters_are_given(): void
    {
        // Arrange
        $qualification = $this->ownedQualification();
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);

        $targetA = $this->target(QualificationTargetStatusEnum::QUALIFIED);
        $targetB = $this->target(QualificationTargetStatusEnum::NOT_QUALIFIED);
        $this->qualificationTargets->method('findByQualificationId')->willReturn([$targetA, $targetB]);

        // Act
        $result = ($this->handler)(new ListQualificationTargetsQuery(
            qualificationId: $this->qualificationId->asString(),
            organizationId: $this->organizationId->asString(),
        ));

        // Assert
        Assert::assertCount(2, $result);
    }

    #[Test]
    public function filters_by_status_via_the_repository_when_status_is_given(): void
    {
        // Arrange
        $qualification = $this->ownedQualification();
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);

        $target = $this->target(QualificationTargetStatusEnum::QUALIFIED);
        $qualificationTargets = $this->createMock(QualificationTargetRepositoryInterface::class);
        $qualificationTargets
            ->expects($this->once())
            ->method('findByQualificationIdAndStatuses')
            ->with($qualification->id(), [QualificationTargetStatusEnum::QUALIFIED])
            ->willReturn([$target]);
        $handler = new ListQualificationTargetsHandler($this->qualifications, $qualificationTargets);

        // Act
        $result = $handler(new ListQualificationTargetsQuery(
            qualificationId: $this->qualificationId->asString(),
            organizationId: $this->organizationId->asString(),
            status: 'qualified',
        ));

        // Assert
        Assert::assertCount(1, $result);
        Assert::assertSame('qualified', $result[0]->status);
    }

    #[Test]
    public function post_filters_to_the_given_project_ids_regardless_of_status_when_project_ids_are_given(): void
    {
        // Arrange
        $qualification = $this->ownedQualification();
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);

        $wanted = $this->target(QualificationTargetStatusEnum::NOT_QUALIFIED);
        $unwanted = $this->target(QualificationTargetStatusEnum::QUALIFIED);
        $this->qualificationTargets->method('findByQualificationId')->willReturn([$wanted, $unwanted]);

        // Act
        $result = ($this->handler)(new ListQualificationTargetsQuery(
            qualificationId: $this->qualificationId->asString(),
            organizationId: $this->organizationId->asString(),
            projectIds: [$wanted->projectId()->asString()],
        ));

        // Assert
        Assert::assertCount(1, $result);
        Assert::assertSame($wanted->id()->asString(), $result[0]->id);
    }

    #[Test]
    public function combines_status_filtering_with_a_project_id_post_filter(): void
    {
        // Arrange
        $qualification = $this->ownedQualification();
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);

        $wanted = $this->target(QualificationTargetStatusEnum::QUALIFIED);
        $unwanted = $this->target(QualificationTargetStatusEnum::QUALIFIED);
        $this->qualificationTargets
            ->method('findByQualificationIdAndStatuses')
            ->willReturn([$wanted, $unwanted]);

        // Act
        $result = ($this->handler)(new ListQualificationTargetsQuery(
            qualificationId: $this->qualificationId->asString(),
            organizationId: $this->organizationId->asString(),
            status: 'qualified',
            projectIds: [$wanted->projectId()->asString()],
        ));

        // Assert
        Assert::assertCount(1, $result);
        Assert::assertSame($wanted->id()->asString(), $result[0]->id);
    }

    #[Test]
    public function throws_not_found_when_the_qualification_does_not_exist_or_belongs_to_another_organization(): void
    {
        // Arrange: the repository scopes the lookup by organization at the query level, so
        // "doesn't exist" and "belongs to someone else" both surface as null here.
        $this->qualifications->method('findByIdForOrganization')->willReturn(null);

        // Assert
        $this->expectException(QualificationNotFoundException::class);

        // Act
        ($this->handler)(new ListQualificationTargetsQuery(
            qualificationId: QualificationId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->qualificationId = QualificationId::generate();
        $this->organizationId = OrganizationId::generate();
        $this->qualifications = $this->createStub(QualificationRepositoryInterface::class);
        $this->qualificationTargets = $this->createStub(QualificationTargetRepositoryInterface::class);
        $this->handler = new ListQualificationTargetsHandler($this->qualifications, $this->qualificationTargets);
    }

    private function ownedQualification(): Qualification
    {
        return Qualification::draft(
            id: $this->qualificationId,
            organizationId: $this->organizationId,
            title: 'Test qualification',
            description: null,
            createdBy: AccountId::generate(),
            criteria: QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?'),
        );
    }

    private function target(QualificationTargetStatusEnum $status): QualificationTarget
    {
        $target = QualificationTarget::create(
            id: QualificationTargetId::generate(),
            qualificationId: $this->qualificationId,
            organizationId: $this->organizationId,
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('1', 'group/project', 'Project', 'main'),
        );

        if (QualificationTargetStatusEnum::QUALIFIED === $status || QualificationTargetStatusEnum::NOT_QUALIFIED === $status) {
            $target->start('runner-job-1');
            $target->recordSuccess(QualificationScore::fromInt(QualificationTargetStatusEnum::QUALIFIED === $status ? 5 : 1), 'result', 'runner-1');
        }

        return $target;
    }
}
