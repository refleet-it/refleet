<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Application\Query\GetQualification;

use App\Qualification\Qualification\Application\Query\GetQualification\GetQualificationHandler;
use App\Qualification\Qualification\Application\Query\GetQualification\GetQualificationQuery;
use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationNotFoundException;
use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\AccountId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationCriteria;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Enum\QualificationTargetStatusEnum;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetQualificationHandler::class)]
final class GetQualificationHandlerTest extends TestCase
{
    private QualificationRepositoryInterface&Stub $qualifications;

    private QualificationTargetRepositoryInterface&Stub $qualificationTargets;

    private GetQualificationHandler $handler;

    #[Test]
    public function returns_the_full_detail_of_an_owned_qualification(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualificationId = QualificationId::generate();

        $qualification = Qualification::draft(
            id: $qualificationId,
            organizationId: $organizationId,
            title: 'Bump acme/legacy-lib to v3',
            description: null,
            createdBy: AccountId::generate(),
            criteria: QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?'),
        );

        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);
        $this->qualificationTargets->method('statusBreakdown')->willReturn([
            QualificationTargetStatusEnum::QUALIFIED->value => 2,
            QualificationTargetStatusEnum::NOT_QUALIFIED->value => 1,
        ]);

        // Act
        $result = ($this->handler)(new GetQualificationQuery(
            qualificationId: $qualificationId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertSame($qualificationId->asString(), $result->id);
        Assert::assertSame('ai', $result->qualificationMode);
        Assert::assertSame('Does this repository depend on acme/legacy-lib?', $result->qualificationPrompt);
        Assert::assertSame(3, $result->targetCount);
        Assert::assertSame(100, $result->progressPercent);
    }

    #[Test]
    public function exposes_the_ai_model_override(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualificationId = QualificationId::generate();

        $qualification = Qualification::draft(
            id: $qualificationId,
            organizationId: $organizationId,
            title: 'AI-driven qualification',
            description: null,
            createdBy: AccountId::generate(),
            criteria: QualificationCriteria::ai('Does this repo use acme/legacy-lib?', 'claude-sonnet-5'),
        );

        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);
        $this->qualificationTargets->method('statusBreakdown')->willReturn([]);

        // Act
        $result = ($this->handler)(new GetQualificationQuery(
            qualificationId: $qualificationId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertSame('claude-sonnet-5', $result->qualificationModel);
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
        ($this->handler)(new GetQualificationQuery(
            qualificationId: QualificationId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->qualifications = $this->createStub(QualificationRepositoryInterface::class);
        $this->qualificationTargets = $this->createStub(QualificationTargetRepositoryInterface::class);
        $this->handler = new GetQualificationHandler($this->qualifications, $this->qualificationTargets);
    }
}
