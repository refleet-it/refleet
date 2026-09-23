<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Application\Command\CreateQualification;

use App\Qualification\Qualification\Application\Command\CreateQualification\CreateQualificationCommand;
use App\Qualification\Qualification\Application\Command\CreateQualification\CreateQualificationHandler;
use App\Qualification\Qualification\Domain\Qualification\Exception\NoTargetProjectsResolvedException;
use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\QualificationTarget\Model\QualificationTarget;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Shared\Domain\Service\ProjectCatalogInterface;
use App\Shared\Domain\ValueObject\ProjectCatalogEntry;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateQualificationHandler::class)]
final class CreateQualificationHandlerTest extends TestCase
{
    private QualificationRepositoryInterface&MockObject $qualifications;

    private QualificationTargetRepositoryInterface&MockObject $qualificationTargets;

    private ProjectCatalogInterface&MockObject $projectCatalog;

    private CreateQualificationHandler $handler;

    #[Test]
    public function drafts_a_qualification_and_a_target_per_project_when_project_ids_are_empty(): void
    {
        // Arrange: empty projectIds resolves to the whole organization fleet
        $project = $this->buildProject();

        $this->projectCatalog
            ->expects($this->once())
            ->method('allForOrganization')
            ->willReturn([$project]);

        $savedQualification = null;
        $this->qualifications
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Qualification $qualification) use (&$savedQualification): bool {
                $savedQualification = $qualification;

                return true;
            }));

        $savedTargets = null;
        $this->qualificationTargets
            ->expects($this->once())
            ->method('saveAll')
            ->with($this->callback(static function (array $targets) use (&$savedTargets): bool {
                $savedTargets = $targets;

                return true;
            }));

        // Act
        $result = ($this->handler)(new CreateQualificationCommand(
            organizationId: '22222222-2222-2222-2222-222222222222',
            title: 'Bump acme/legacy-lib to v3',
            description: 'Removes the deprecated dependency across the fleet.',
            createdByAccountId: '33333333-3333-3333-3333-333333333333',
            qualificationMode: 'ai',
            qualificationPrompt: 'Does this repository depend on acme/legacy-lib?',
            projectIds: null,
        ));

        // Assert
        Assert::assertNotNull($savedQualification);
        Assert::assertCount(1, $savedTargets);
        Assert::assertInstanceOf(QualificationTarget::class, $savedTargets[0]);
        Assert::assertSame('Bump acme/legacy-lib to v3', $result->title);
        Assert::assertSame(1, $result->targetCount);
        Assert::assertSame('ai', $result->qualificationMode);
    }

    #[Test]
    public function resolves_explicit_project_ids_through_list_projects_by_ids(): void
    {
        // Arrange
        $project = $this->buildProject();

        $this->projectCatalog
            ->expects($this->once())
            ->method('byIds')
            ->willReturn([$project]);

        $this->qualifications->expects($this->once())->method('save');
        $this->qualificationTargets->expects($this->once())->method('saveAll');

        // Act
        $result = ($this->handler)(new CreateQualificationCommand(
            organizationId: '22222222-2222-2222-2222-222222222222',
            title: 'Bump acme/legacy-lib to v3',
            description: null,
            createdByAccountId: '33333333-3333-3333-3333-333333333333',
            qualificationMode: 'ai',
            qualificationPrompt: 'Does this repository depend on acme/legacy-lib?',
            projectIds: ['11111111-1111-1111-1111-111111111111'],
        ));

        // Assert
        Assert::assertSame(1, $result->targetCount);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function drafts_a_qualification_with_ai_criteria_and_a_model_override(): void
    {
        // Arrange
        $project = $this->buildProject();

        $this->projectCatalog
            ->method('allForOrganization')
            ->willReturn([$project]);

        $savedQualification = null;
        $this->qualifications
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static function (Qualification $qualification) use (&$savedQualification): bool {
                $savedQualification = $qualification;

                return true;
            }));
        $this->qualificationTargets->expects($this->once())->method('saveAll');

        // Act
        ($this->handler)(new CreateQualificationCommand(
            organizationId: '22222222-2222-2222-2222-222222222222',
            title: 'AI qualified check',
            description: null,
            createdByAccountId: '33333333-3333-3333-3333-333333333333',
            qualificationMode: 'ai',
            qualificationPrompt: 'Does this repo use acme/legacy-lib?',
            qualificationModel: 'claude-opus-5',
            projectIds: null,
        ));

        // Assert
        Assert::assertSame('claude-opus-5', $savedQualification->criteria()->model());
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_when_the_resolved_project_list_is_empty(): void
    {
        // Arrange
        $this->projectCatalog
            ->method('allForOrganization')
            ->willReturn([]);

        $this->qualifications->expects($this->never())->method('save');

        // Assert
        $this->expectException(NoTargetProjectsResolvedException::class);

        // Act
        ($this->handler)(new CreateQualificationCommand(
            organizationId: '22222222-2222-2222-2222-222222222222',
            title: 'Bump acme/legacy-lib to v3',
            description: null,
            createdByAccountId: '33333333-3333-3333-3333-333333333333',
            qualificationMode: 'ai',
            qualificationPrompt: 'Does this repository depend on acme/legacy-lib?',
            projectIds: null,
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->qualifications = $this->createMock(QualificationRepositoryInterface::class);
        $this->qualificationTargets = $this->createMock(QualificationTargetRepositoryInterface::class);
        $this->projectCatalog = $this->createMock(ProjectCatalogInterface::class);
        $this->handler = new CreateQualificationHandler($this->qualifications, $this->qualificationTargets, $this->projectCatalog);
    }

    private function buildProject(): ProjectCatalogEntry
    {
        return new ProjectCatalogEntry(
            id: '11111111-1111-1111-1111-111111111111',
            externalId: '48210942',
            name: 'Payments Service',
            path: 'backend-team/payments-service',
            defaultBranch: 'main',
        );
    }
}
