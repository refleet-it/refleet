<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Application\Command\StartQualification;

use App\Qualification\Qualification\Application\Command\StartQualification\StartQualificationCommand;
use App\Qualification\Qualification\Application\Command\StartQualification\StartQualificationHandler;
use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationNotFoundException;
use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\Service\QualificationJobPayloadFactory;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\AccountId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationCriteria;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Model\QualificationTarget;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\ProjectId;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\QualificationTargetId;
use App\Qualification\Qualification\Infrastructure\Bus\RunnerJobRequested\RunnerJobRequestedMessage;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(StartQualificationHandler::class)]
final class StartQualificationHandlerTest extends TestCase
{
    private QualificationRepositoryInterface&MockObject $qualifications;

    private QualificationTargetRepositoryInterface&MockObject $qualificationTargets;

    private MessageBusInterface&MockObject $bus;

    private StartQualificationHandler $handler;

    #[Test]
    public function starts_the_qualification_and_enqueues_a_runner_job_per_pending_target(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualificationId = QualificationId::generate();
        $qualification = $this->draftedQualification($qualificationId, $organizationId);
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);
        $this->qualifications->expects($this->once())->method('save')->with($qualification);

        $target = QualificationTarget::create(
            id: QualificationTargetId::generate(),
            qualificationId: $qualificationId,
            organizationId: $organizationId,
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('1', 'group/project', 'Project', 'main'),
        );
        $this->qualificationTargets->method('findByQualificationIdAndStatuses')->willReturn([$target]);

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(RunnerJobRequestedMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        $this->qualificationTargets
            ->expects($this->once())
            ->method('saveAll')
            ->with([$target]);

        // Act
        ($this->handler)(new StartQualificationCommand(
            qualificationId: $qualificationId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertNotNull($target->runnerJobId());
    }

    #[Test]
    public function does_not_touch_the_bus_when_there_are_no_pending_targets(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualificationId = QualificationId::generate();
        $qualification = $this->draftedQualification($qualificationId, $organizationId);
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);
        $this->qualifications->expects($this->once())->method('save');

        $this->qualificationTargets->method('findByQualificationIdAndStatuses')->willReturn([]);
        $this->bus->expects($this->never())->method('dispatch');
        $this->qualificationTargets->expects($this->never())->method('saveAll');

        // Act
        ($this->handler)(new StartQualificationCommand(
            qualificationId: $qualificationId->asString(),
            organizationId: $organizationId->asString(),
        ));
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
        ($this->handler)(new StartQualificationCommand(
            qualificationId: QualificationId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function defaults_the_ai_job_payload_engine_to_claude_when_none_was_selected(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualificationId = QualificationId::generate();
        $qualification = $this->draftedAiQualification($qualificationId, $organizationId, null);
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);

        $target = QualificationTarget::create(
            id: QualificationTargetId::generate(),
            qualificationId: $qualificationId,
            organizationId: $organizationId,
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('1', 'group/project', 'Project', 'main'),
        );
        $this->qualificationTargets->method('findByQualificationIdAndStatuses')->willReturn([$target]);

        $dispatchedCommand = null;
        $this->bus
            ->method('dispatch')
            ->willReturnCallback(static function (RunnerJobRequestedMessage $command) use (&$dispatchedCommand): Envelope {
                $dispatchedCommand = $command;

                return new Envelope(new \stdClass());
            });

        // Act
        ($this->handler)(new StartQualificationCommand(
            qualificationId: $qualificationId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertSame('claude', $dispatchedCommand->payload['engine']);
        Assert::assertSame('claude', $dispatchedCommand->engine);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function carries_the_selected_kiro_engine_in_the_enqueued_ai_job_payload(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualificationId = QualificationId::generate();
        $qualification = $this->draftedAiQualification($qualificationId, $organizationId, CriteriaEngineEnum::KIRO);
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);

        $target = QualificationTarget::create(
            id: QualificationTargetId::generate(),
            qualificationId: $qualificationId,
            organizationId: $organizationId,
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('1', 'group/project', 'Project', 'main'),
        );
        $this->qualificationTargets->method('findByQualificationIdAndStatuses')->willReturn([$target]);

        $dispatchedCommand = null;
        $this->bus
            ->method('dispatch')
            ->willReturnCallback(static function (RunnerJobRequestedMessage $command) use (&$dispatchedCommand): Envelope {
                $dispatchedCommand = $command;

                return new Envelope(new \stdClass());
            });

        // Act
        ($this->handler)(new StartQualificationCommand(
            qualificationId: $qualificationId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertSame('kiro', $dispatchedCommand->payload['engine']);
        Assert::assertSame('kiro', $dispatchedCommand->engine);
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->qualifications = $this->createMock(QualificationRepositoryInterface::class);
        $this->qualificationTargets = $this->createMock(QualificationTargetRepositoryInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->handler = new StartQualificationHandler($this->qualifications, $this->qualificationTargets, $this->bus, new QualificationJobPayloadFactory());
    }

    private function draftedQualification(QualificationId $id, OrganizationId $organizationId): Qualification
    {
        return Qualification::draft(
            id: $id,
            organizationId: $organizationId,
            title: 'Test qualification',
            description: null,
            createdBy: AccountId::generate(),
            criteria: QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?'),
        );
    }

    private function draftedAiQualification(QualificationId $id, OrganizationId $organizationId, ?CriteriaEngineEnum $engine): Qualification
    {
        return Qualification::draft(
            id: $id,
            organizationId: $organizationId,
            title: 'Test AI qualification',
            description: null,
            createdBy: AccountId::generate(),
            criteria: QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?', null, $engine),
        );
    }
}
