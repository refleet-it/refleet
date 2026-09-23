<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Application\Query\ListQualificationTargetsPage;

use App\Qualification\Qualification\Application\Query\ListQualificationTargets\QualificationTargetOverview;
use App\Qualification\Qualification\Application\Query\ListQualificationTargetsPage\ListQualificationTargetsPageHandler;
use App\Qualification\Qualification\Application\Query\ListQualificationTargetsPage\ListQualificationTargetsPageQuery;
use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationNotFoundException;
use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\AccountId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationCriteria;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Model\QualificationTarget;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\ProjectId;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\QualificationTargetId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListQualificationTargetsPageHandler::class)]
final class ListQualificationTargetsPageHandlerTest extends TestCase
{
    private QualificationRepositoryInterface&Stub $qualifications;

    private QualificationTargetRepositoryInterface&Stub $qualificationTargets;

    private ListQualificationTargetsPageHandler $handler;

    #[Test]
    public function lists_a_page_of_targets_for_an_owned_qualification(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualification = $this->draftedQualification($organizationId);
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);

        $target = $this->newTarget($qualification->id());
        $this->qualificationTargets
            ->method('getPaginatedListByQualificationId')
            ->willReturn(ListResponse::create([$target], 1, PaginationParameters::fromRequest()));

        // Act
        $result = ($this->handler)(new ListQualificationTargetsPageQuery(
            qualificationId: $qualification->id()->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertCount(1, $result->getItems());
        Assert::assertInstanceOf(QualificationTargetOverview::class, $result->getItems()[0]);
        Assert::assertSame('Payments Service', $result->getItems()[0]->projectSnapshotName);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_not_found_when_the_qualification_does_not_exist_or_belongs_to_another_organization(): void
    {
        // Arrange: the repository scopes the lookup by organization at the query level, so
        // "doesn't exist" and "belongs to someone else" both surface as null here.
        $this->qualifications->method('findByIdForOrganization')->willReturn(null);

        // Assert
        $this->expectException(QualificationNotFoundException::class);

        // Act
        ($this->handler)(new ListQualificationTargetsPageQuery(
            qualificationId: QualificationId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->qualifications = $this->createStub(QualificationRepositoryInterface::class);
        $this->qualificationTargets = $this->createStub(QualificationTargetRepositoryInterface::class);
        $this->handler = new ListQualificationTargetsPageHandler($this->qualifications, $this->qualificationTargets);
    }

    private function draftedQualification(OrganizationId $organizationId): Qualification
    {
        return Qualification::draft(
            id: QualificationId::generate(),
            organizationId: $organizationId,
            title: 'Bump acme/legacy-lib to v3',
            description: null,
            createdBy: AccountId::generate(),
            criteria: QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?'),
        );
    }

    private function newTarget(QualificationId $qualificationId): QualificationTarget
    {
        return QualificationTarget::create(
            id: QualificationTargetId::generate(),
            qualificationId: $qualificationId,
            organizationId: OrganizationId::generate(),
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('48210942', 'backend-team/payments-service', 'Payments Service', 'main'),
        );
    }
}
