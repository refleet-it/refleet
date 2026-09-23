<?php

declare(strict_types=1);

namespace App\Tests\Unit\Qualification\Qualification\Application\Command\CancelQualification;

use App\Qualification\Qualification\Application\Command\CancelQualification\CancelQualificationCommand;
use App\Qualification\Qualification\Application\Command\CancelQualification\CancelQualificationHandler;
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
use App\Qualification\Qualification\Infrastructure\Bus\OwnerJobsCancellationRequested\OwnerJobsCancellationRequestedMessage;
use App\Shared\Domain\ValueObject\ProjectSnapshot;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(CancelQualificationHandler::class)]
final class CancelQualificationHandlerTest extends TestCase
{
    private QualificationRepositoryInterface&MockObject $qualifications;

    private QualificationTargetRepositoryInterface&MockObject $qualificationTargets;

    private MessageBusInterface&MockObject $bus;

    private CancelQualificationHandler $handler;

    #[Test]
    public function cancels_the_qualification_its_non_terminal_targets_and_its_runner_jobs(): void
    {
        // Arrange
        $organizationId = OrganizationId::generate();
        $qualificationId = QualificationId::generate();
        $qualification = Qualification::draft(
            id: $qualificationId,
            organizationId: $organizationId,
            title: 'Test qualification',
            description: null,
            createdBy: AccountId::generate(),
            criteria: QualificationCriteria::ai('Does this repository depend on acme/legacy-lib?'),
        );
        $this->qualifications->method('findByIdForOrganization')->willReturn($qualification);
        $this->qualifications->expects($this->once())->method('save')->with($qualification);

        $target = QualificationTarget::create(
            id: QualificationTargetId::generate(),
            qualificationId: $qualificationId,
            organizationId: $organizationId,
            projectId: ProjectId::generate(),
            snapshot: new ProjectSnapshot('1', 'group/project', 'Project', 'main'),
        );
        $this->qualificationTargets->method('findNonTerminalByQualificationId')->willReturn([$target]);
        $this->qualificationTargets->expects($this->once())->method('saveAll')->with([$target]);

        $this->bus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(static function (OwnerJobsCancellationRequestedMessage $command) use ($qualificationId, $organizationId): bool {
                Assert::assertSame($qualificationId->asString(), $command->ownerId);
                Assert::assertSame($organizationId->asString(), $command->organizationId);

                return true;
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        // Act
        ($this->handler)(new CancelQualificationCommand(
            qualificationId: $qualificationId->asString(),
            organizationId: $organizationId->asString(),
            reason: 'No longer needed',
        ));

        // Assert
        Assert::assertSame('cancelled', $qualification->status()->value);
        Assert::assertSame('cancelled', $target->status()->value);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function throws_not_found_when_the_qualification_does_not_exist_or_belongs_to_another_organization(): void
    {
        // Arrange: the repository scopes the lookup by organization at the query level, so
        // "doesn't exist" and "belongs to someone else" both surface as null here.
        $this->qualifications->method('findByIdForOrganization')->willReturn(null);

        $this->qualifications->expects($this->never())->method('save');
        $this->qualificationTargets->expects($this->never())->method('saveAll');
        $this->bus->expects($this->never())->method('dispatch');

        // Assert
        $this->expectException(QualificationNotFoundException::class);

        // Act
        ($this->handler)(new CancelQualificationCommand(
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
        $this->handler = new CancelQualificationHandler($this->qualifications, $this->qualificationTargets, $this->bus);
    }
}
