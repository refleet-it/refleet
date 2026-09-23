<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Command\RetryQualificationTargets;

use App\Qualification\Qualification\Domain\Qualification\Enum\QualificationStatusEnum;
use App\Qualification\Qualification\Domain\Qualification\Exception\InvalidQualificationStateTransitionException;
use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationArchivedException;
use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationNotFoundException;
use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\Service\QualificationJobPayloadFactory;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Enum\QualificationTargetStatusEnum;
use App\Qualification\Qualification\Domain\QualificationTarget\Exception\QualificationTargetNotFoundException;
use App\Qualification\Qualification\Domain\QualificationTarget\Model\QualificationTarget;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\QualificationTargetId;
use App\Qualification\Qualification\Infrastructure\Bus\RunnerJobRequested\RunnerJobRequestedMessage;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\ValueObject\Id;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class RetryQualificationTargetsHandler
{
    public function __construct(
        private QualificationRepositoryInterface $qualifications,
        private QualificationTargetRepositoryInterface $qualificationTargets,
        private MessageBusInterface $bus,
        private QualificationJobPayloadFactory $payloadFactory,
    ) {
    }

    public function __invoke(RetryQualificationTargetsCommand $command): void
    {
        $qualification = $this->findOwnedQualification($command->qualificationId, $command->organizationId);

        $targets = null !== $command->targetId
            ? [$this->findOwnedTarget($command->targetId, $qualification)]
            : $this->qualificationTargets->findByQualificationIdAndStatuses($qualification->id(), [QualificationTargetStatusEnum::FAILED]);

        if ([] === $targets) {
            return;
        }

        $this->reopen($qualification);

        $criteria = $qualification->criteria();

        foreach ($targets as $target) {
            $target->retry();

            $jobId = Id::generate()->asString();

            $this->bus->dispatch(new RunnerJobRequestedMessage(
                jobId: $jobId,
                ownerId: $qualification->id()->asString(),
                ownerTargetId: $target->id()->asString(),
                ownerLabel: $qualification->title(),
                organizationId: $qualification->organizationId()->asString(),
                kind: 'qualification',
                mode: $criteria->mode()->value,
                payload: $this->payloadFactory->build($criteria, $target->projectSnapshot()),
                engine: ($criteria->engine() ?? CriteriaEngineEnum::CLAUDE)->value,
            ));

            $target->start($jobId);
        }

        $this->qualificationTargets->saveAll($targets);
    }

    /**
     * A running batch simply absorbs the retried targets; a completed one is re-opened so
     * it completes (and notifies) again once they settle. Anything else has no runner
     * work to resume.
     */
    private function reopen(Qualification $qualification): void
    {
        if ($qualification->isArchived()) {
            throw new QualificationArchivedException();
        }

        if (QualificationStatusEnum::RUNNING === $qualification->status()) {
            return;
        }

        if (QualificationStatusEnum::COMPLETED !== $qualification->status()) {
            throw new InvalidQualificationStateTransitionException($qualification->status(), 'retry');
        }

        $qualification->resume();
        $this->qualifications->save($qualification);
    }

    private function findOwnedQualification(string $qualificationId, string $organizationId): Qualification
    {
        $qualification = $this->qualifications->findByIdForOrganization(QualificationId::fromString($qualificationId), OrganizationId::fromString($organizationId));

        if (null === $qualification) {
            throw new QualificationNotFoundException();
        }

        return $qualification;
    }

    private function findOwnedTarget(string $targetId, Qualification $qualification): QualificationTarget
    {
        $target = $this->qualificationTargets->findById(QualificationTargetId::fromString($targetId));

        if (null === $target || !$target->qualificationId()->equals($qualification->id())) {
            throw new QualificationTargetNotFoundException();
        }

        return $target;
    }
}
