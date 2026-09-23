<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Application\Command\RetryQualificationTargets;

use App\Qualification\Qualification\Application\Command\RetryQualificationTargets\RetryQualificationTargetsCommand;
use App\Qualification\Qualification\Application\Command\RetryQualificationTargets\RetryQualificationTargetsHandler;
use App\Qualification\Qualification\Domain\Qualification\Enum\QualificationStatusEnum;
use App\Qualification\Qualification\Domain\Qualification\Exception\InvalidQualificationStateTransitionException;
use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationArchivedException;
use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationNotFoundException;
use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\Service\QualificationJobPayloadFactory;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\AccountId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationCriteria;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationScore;
use App\Qualification\Qualification\Domain\QualificationTarget\Enum\QualificationTargetStatusEnum;
use App\Qualification\Qualification\Domain\QualificationTarget\Exception\InvalidQualificationTargetStateTransitionException;
use App\Qualification\Qualification\Domain\QualificationTarget\Exception\QualificationTargetNotFoundException;
use App\Qualification\Qualification\Domain\QualificationTarget\Model\QualificationTarget;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\ProjectId;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\QualificationTargetId;
use App\Qualification\Qualification\Infrastructure\Bus\RunnerJobRequested\RunnerJobRequestedMessage;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(RetryQualificationTargetsHandler::class)]
final class RetryQualificationTargetsHandlerTest extends TestCase
{
    private QualificationRepositoryInterface&MockObject $qualifications;

    private QualificationTargetRepositoryInterface&MockObject $qualificationTargets;

    private MessageBusInterface&MockObject $bus;

    private RetryQualificationTargetsHandler $handler;

    #[Test]
    public function reopens_a_completed_qualification_and_enqueues_a_fresh_job_per_failed_target(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualificationId = QualificationId::generate();
        $qualification = $this->qualification($qualificationId, $organizationId);
        $qualification->start();
        $qualification->complete();
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);
        $this->qualifications->expects($this->once())->method('save')->with($qualification);

        $failed = $this->failedTarget($qualificationId, $organizationId, 'runner-job-1');
        $this->qualificationTargets
            ->expects($this->once())
            ->method('findByQualificationIdAndStatuses')
            ->with($qualificationId, [QualificationTargetStatusEnum::FAILED])
            ->willReturn([$failed]);

        $dispatched = [];
        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (RunnerJobRequestedMessage $message) use (&$dispatched): Envelope {
                $dispatched[] = $message;

                return new Envelope(new \stdClass());
            });
        $this->qualificationTargets->expects($this->once())->method('saveAll')->with([$failed]);

        // Act
        ($this->handler)(new RetryQualificationTargetsCommand(
            qualificationId: $qualificationId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertSame(QualificationStatusEnum::RUNNING, $qualification->status());
        Assert::assertSame(QualificationTargetStatusEnum::IN_PROGRESS, $failed->status());
        Assert::assertNotSame('runner-job-1', $failed->runnerJobId());
        Assert::assertSame($failed->runnerJobId(), $dispatched[0]->jobId);
        Assert::assertSame($failed->id()->asString(), $dispatched[0]->ownerTargetId);
        Assert::assertSame('qualification', $dispatched[0]->kind);
        Assert::assertSame('ai', $dispatched[0]->payload['mode']);
        Assert::assertStringContainsString('{"score": <integer 1-5>', (string) $dispatched[0]->payload['prompt']);
    }

    #[Test]
    public function retries_a_single_target_of_a_still_running_qualification_without_touching_the_aggregate(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualificationId = QualificationId::generate();
        $qualification = $this->qualification($qualificationId, $organizationId);
        $qualification->start();
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);
        $this->qualifications->expects($this->never())->method('save');

        $failed = $this->failedTarget($qualificationId, $organizationId, 'runner-job-1');
        $this->qualificationTargets->method('findById')->with($failed->id())->willReturn($failed);
        $this->qualificationTargets->expects($this->never())->method('findByQualificationIdAndStatuses');

        $this->bus->expects($this->once())->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $this->qualificationTargets->expects($this->once())->method('saveAll')->with([$failed]);

        // Act
        ($this->handler)(new RetryQualificationTargetsCommand(
            qualificationId: $qualificationId->asString(),
            organizationId: $organizationId->asString(),
            targetId: $failed->id()->asString(),
        ));

        // Assert
        Assert::assertSame(QualificationStatusEnum::RUNNING, $qualification->status());
        Assert::assertSame(QualificationTargetStatusEnum::IN_PROGRESS, $failed->status());
    }

    #[Test]
    public function does_nothing_when_no_target_failed(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualificationId = QualificationId::generate();
        $qualification = $this->qualification($qualificationId, $organizationId);
        $qualification->start();
        $qualification->complete();
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);
        $this->qualifications->expects($this->never())->method('save');
        $this->qualificationTargets->method('findByQualificationIdAndStatuses')->willReturn([]);
        $this->bus->expects($this->never())->method('dispatch');
        $this->qualificationTargets->expects($this->never())->method('saveAll');

        // Act
        ($this->handler)(new RetryQualificationTargetsCommand(
            qualificationId: $qualificationId->asString(),
            organizationId: $organizationId->asString(),
        ));

        // Assert
        Assert::assertSame(QualificationStatusEnum::COMPLETED, $qualification->status());
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function refuses_a_target_that_did_not_fail(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualificationId = QualificationId::generate();
        $qualification = $this->qualification($qualificationId, $organizationId);
        $qualification->start();
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);

        $target = $this->target($qualificationId, $organizationId);
        $target->start('runner-job-1');
        $target->recordSuccess(QualificationScore::fromInt(2), 'No match', 'runner-1');
        $this->qualificationTargets->method('findById')->willReturn($target);
        $this->bus->expects($this->never())->method('dispatch');

        // Assert
        $this->expectException(InvalidQualificationTargetStateTransitionException::class);

        // Act
        ($this->handler)(new RetryQualificationTargetsCommand(
            qualificationId: $qualificationId->asString(),
            organizationId: $organizationId->asString(),
            targetId: $target->id()->asString(),
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function refuses_a_target_that_belongs_to_another_qualification(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualificationId = QualificationId::generate();
        $qualification = $this->qualification($qualificationId, $organizationId);
        $qualification->start();
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);

        $foreign = $this->failedTarget(QualificationId::generate(), $organizationId, 'runner-job-1');
        $this->qualificationTargets->method('findById')->willReturn($foreign);

        // Assert
        $this->expectException(QualificationTargetNotFoundException::class);

        // Act
        ($this->handler)(new RetryQualificationTargetsCommand(
            qualificationId: $qualificationId->asString(),
            organizationId: $organizationId->asString(),
            targetId: $foreign->id()->asString(),
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function refuses_a_cancelled_qualification(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualificationId = QualificationId::generate();
        $qualification = $this->qualification($qualificationId, $organizationId);
        $qualification->start();
        $qualification->cancel(null);
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);
        $this->qualificationTargets->method('findByQualificationIdAndStatuses')->willReturn([
            $this->failedTarget($qualificationId, $organizationId, 'runner-job-1'),
        ]);
        $this->bus->expects($this->never())->method('dispatch');

        // Assert
        $this->expectException(InvalidQualificationStateTransitionException::class);

        // Act
        ($this->handler)(new RetryQualificationTargetsCommand(
            qualificationId: $qualificationId->asString(),
            organizationId: $organizationId->asString(),
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function refuses_an_archived_qualification(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualificationId = QualificationId::generate();
        $qualification = $this->qualification($qualificationId, $organizationId);
        $qualification->start();
        $qualification->complete();
        $qualification->archive(new \DateTimeImmutable());
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);
        $this->qualificationTargets->method('findByQualificationIdAndStatuses')->willReturn([
            $this->failedTarget($qualificationId, $organizationId, 'runner-job-1'),
        ]);
        $this->bus->expects($this->never())->method('dispatch');

        // Assert
        $this->expectException(QualificationArchivedException::class);

        // Act
        ($this->handler)(new RetryQualificationTargetsCommand(
            qualificationId: $qualificationId->asString(),
            organizationId: $organizationId->asString(),
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_not_found_when_the_qualification_does_not_exist_or_belongs_to_another_organization(): void
    {
        // Arrange
        $this->qualifications->method('findByIdForOrganization')->willReturn(null);

        // Assert
        $this->expectException(QualificationNotFoundException::class);

        // Act
        ($this->handler)(new RetryQualificationTargetsCommand(
            qualificationId: QualificationId::generate()->asString(),
            organizationId: OrganizationId::generate()->asString(),
        ));
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->qualifications = $this->createMock(QualificationRepositoryInterface::class);
        $this->qualificationTargets = $this->createMock(QualificationTargetRepositoryInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->handler = new RetryQualificationTargetsHandler(
            $this->qualifications,
            $this->qualificationTargets,
            $this->bus,
            new QualificationJobPayloadFactory(),
        );
    }

    private function qualification(QualificationId $id, OrganizationId $organizationId): Qualification
    {
        return Qualification::draft(
            id: $id,
            organizationId: $organizationId,
            title: 'Find projects depending on acme/legacy-lib',
            description: null,
            createdBy: AccountId::generate(),
            criteria: QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?', null, null),
        );
    }

    private function target(QualificationId $qualificationId, OrganizationId $organizationId): QualificationTarget
    {
        return QualificationTarget::create(
            id: QualificationTargetId::generate(),
            qualificationId: $qualificationId,
            organizationId: $organizationId,
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('1', 'group/project', 'Project', 'main'),
        );
    }

    private function failedTarget(QualificationId $qualificationId, OrganizationId $organizationId, string $jobId): QualificationTarget
    {
        $target = $this->target($qualificationId, $organizationId);
        $target->start($jobId);
        $target->recordFailure('Agent did not return a clear qualification decision', 'runner-1');

        return $target;
    }
}
