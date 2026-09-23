<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Command\RecordQualificationTargetResult;

use App\Qualification\Qualification\Domain\Qualification\Exception\InvalidQualificationStateTransitionException;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationScore;
use App\Qualification\Qualification\Domain\QualificationTarget\Enum\QualificationTargetStatusEnum;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\QualificationTargetId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordQualificationTargetResultHandler
{
    public function __construct(
        private QualificationTargetRepositoryInterface $qualificationTargets,
        private QualificationRepositoryInterface $qualifications,
    ) {
    }

    public function __invoke(RecordQualificationTargetResultCommand $command): void
    {
        $target = $this->qualificationTargets->findById(QualificationTargetId::fromString($command->targetId));

        if (null === $target) {
            return;
        }

        if ($command->success && null !== $command->score) {
            $target->recordSuccess(QualificationScore::fromInt($command->score), $command->summary, $command->runnerName);
        } elseif ($command->success) {
            $target->recordFailure('Runner reported success without a qualification score', $command->runnerName);
        } else {
            $target->recordFailure($command->errorMessage ?? $command->summary, $command->runnerName);
        }

        $this->qualificationTargets->save($target);

        $this->completeIfFinished(QualificationId::fromString($command->qualificationId));
    }

    private function completeIfFinished(QualificationId $qualificationId): void
    {
        $remaining = $this->qualificationTargets->countByQualificationIdAndStatuses(
            $qualificationId,
            QualificationTargetStatusEnum::nonTerminalStatuses(),
        );

        if ($remaining > 0) {
            return;
        }

        $qualification = $this->qualifications->findById($qualificationId);

        if (null === $qualification) {
            return;
        }

        try {
            $qualification->complete();
            $this->qualifications->save($qualification);
        } catch (InvalidQualificationStateTransitionException) {
            // Possible race with a parallel cancel() — safe to ignore, already left RUNNING.
        }
    }
}
