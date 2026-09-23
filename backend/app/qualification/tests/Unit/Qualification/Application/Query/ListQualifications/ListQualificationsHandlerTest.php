<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Application\Query\ListQualifications;

use App\Qualification\Qualification\Application\Query\ListQualifications\ListQualificationsHandler;
use App\Qualification\Qualification\Application\Query\ListQualifications\ListQualificationsQuery;
use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\AccountId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationCriteria;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Enum\QualificationTargetStatusEnum;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListQualificationsHandler::class)]
final class ListQualificationsHandlerTest extends TestCase
{
    private QualificationRepositoryInterface&Stub $qualifications;

    private QualificationTargetRepositoryInterface&Stub $qualificationTargets;

    private ListQualificationsHandler $handler;

    #[Test]
    public function lists_qualifications_with_a_progress_percentage_derived_from_the_status_breakdown(): void
    {
        // Arrange
        $qualification = $this->draftedQualification();

        $this->qualifications
            ->method('getPaginatedList')
            ->willReturn(ListResponse::create(
                items: [$qualification],
                totalItems: 1,
                pagination: PaginationParameters::fromRequest(),
            ));
        $this->qualificationTargets->method('statusBreakdown')->willReturn([
            QualificationTargetStatusEnum::PENDING->value => 1,
            QualificationTargetStatusEnum::QUALIFIED->value => 3,
        ]);

        // Act
        $result = ($this->handler)(new ListQualificationsQuery($qualification->organizationId()->asString()));

        // Assert
        Assert::assertCount(1, $result->getItems());
        Assert::assertSame(4, $result->getItems()[0]->targetCount);
        Assert::assertSame(3, $result->getItems()[0]->terminalTargetCount);
        Assert::assertSame(75, $result->getItems()[0]->progressPercent);
        Assert::assertSame('ai', $result->getItems()[0]->qualificationMode);
    }

    #[Test]
    public function counts_only_terminal_statuses_towards_the_terminal_target_count(): void
    {
        // Arrange
        $qualification = $this->draftedQualification();

        $this->qualifications
            ->method('getPaginatedList')
            ->willReturn(ListResponse::create(
                items: [$qualification],
                totalItems: 1,
                pagination: PaginationParameters::fromRequest(),
            ));
        $this->qualificationTargets->method('statusBreakdown')->willReturn([
            QualificationTargetStatusEnum::QUALIFIED->value => 2,
            QualificationTargetStatusEnum::NOT_QUALIFIED->value => 1,
            QualificationTargetStatusEnum::IN_PROGRESS->value => 1,
        ]);

        // Act
        $result = ($this->handler)(new ListQualificationsQuery(OrganizationId::generate()->asString()));

        // Assert
        Assert::assertSame(4, $result->getItems()[0]->targetCount);
        Assert::assertSame(3, $result->getItems()[0]->terminalTargetCount);
    }

    #[Test]
    public function reports_no_progress_at_all_when_a_qualification_has_no_targets(): void
    {
        // Arrange
        $qualification = $this->draftedQualification(QualificationCriteria::ai('Does this depend on acme/legacy-lib?'));

        $this->qualifications
            ->method('getPaginatedList')
            ->willReturn(ListResponse::create(
                items: [$qualification],
                totalItems: 1,
                pagination: PaginationParameters::fromRequest(),
            ));
        $this->qualificationTargets->method('statusBreakdown')->willReturn([]);

        // Act
        $result = ($this->handler)(new ListQualificationsQuery(OrganizationId::generate()->asString()));

        // Assert
        Assert::assertSame(0, $result->getItems()[0]->targetCount);
        Assert::assertSame(0, $result->getItems()[0]->terminalTargetCount);
        Assert::assertNull($result->getItems()[0]->progressPercent);
        Assert::assertSame('ai', $result->getItems()[0]->qualificationMode);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->qualifications = $this->createStub(QualificationRepositoryInterface::class);
        $this->qualificationTargets = $this->createStub(QualificationTargetRepositoryInterface::class);
        $this->handler = new ListQualificationsHandler($this->qualifications, $this->qualificationTargets);
    }

    private function draftedQualification(?QualificationCriteria $criteria = null): Qualification
    {
        return Qualification::draft(
            id: QualificationId::generate(),
            organizationId: OrganizationId::generate(),
            title: 'Bump acme/legacy-lib to v3',
            description: null,
            createdBy: AccountId::generate(),
            criteria: $criteria ?? QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?'),
        );
    }
}
