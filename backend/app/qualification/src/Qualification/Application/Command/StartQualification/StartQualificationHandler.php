<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Command\StartQualification;

use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationNotFoundException;
use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\Service\QualificationJobPayloadFactory;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Enum\QualificationTargetStatusEnum;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Qualification\Qualification\Infrastructure\Bus\RunnerJobRequested\RunnerJobRequestedMessage;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\ValueObject\Id;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class StartQualificationHandler
{
    public function __construct(
        private QualificationRepositoryInterface $qualifications,
        private QualificationTargetRepositoryInterface $qualificationTargets,
        private MessageBusInterface $bus,
        private QualificationJobPayloadFactory $payloadFactory,
    ) {
    }

    public function __invoke(StartQualificationCommand $command): void
    {
        $qualification = $this->findOwnedQualification($command->qualificationId, $command->organizationId);

        $qualification->start();

        $this->qualifications->save($qualification);

        $targets = $this->qualificationTargets->findByQualificationIdAndStatuses(
            $qualification->id(),
            [QualificationTargetStatusEnum::PENDING],
        );

        if ([] === $targets) {
            return;
        }

        $criteria = $qualification->criteria();

        foreach ($targets as $target) {
            // The job id is minted here so the target can record it right away; the Runner
            // context picks it up from the message instead of replying with one.
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

    private function findOwnedQualification(string $qualificationId, string $organizationId): Qualification
    {
        $qualification = $this->qualifications->findByIdForOrganization(QualificationId::fromString($qualificationId), OrganizationId::fromString($organizationId));

        if (null === $qualification) {
            throw new QualificationNotFoundException();
        }

        return $qualification;
    }
}
