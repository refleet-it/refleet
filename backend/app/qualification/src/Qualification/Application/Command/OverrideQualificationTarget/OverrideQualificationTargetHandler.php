<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Command\OverrideQualificationTarget;

use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationArchivedException;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Exception\QualificationTargetNotFoundException;
use App\Qualification\Qualification\Domain\QualificationTarget\Model\QualificationTarget;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\QualificationTargetId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class OverrideQualificationTargetHandler
{
    public function __construct(
        private QualificationTargetRepositoryInterface $qualificationTargets,
        private QualificationRepositoryInterface $qualifications,
    ) {
    }

    public function __invoke(OverrideQualificationTargetCommand $command): void
    {
        $target = $this->findOwnedTarget($command->targetId, $command->qualificationId, $command->organizationId);

        // The target is owned by the qualification, so an archived qualification freezes its
        // targets too — the frozen state lives on the aggregate root, not on each target.
        if ($this->qualifications->findById($target->qualificationId())?->isArchived() ?? false) {
            throw new QualificationArchivedException();
        }

        $target->override($command->qualified, $command->note);

        $this->qualificationTargets->save($target);
    }

    private function findOwnedTarget(string $targetId, string $qualificationId, string $organizationId): QualificationTarget
    {
        $target = $this->qualificationTargets->findById(QualificationTargetId::fromString($targetId));

        if (
            null === $target
            || $target->qualificationId()->asString() !== $qualificationId
            || !$target->organizationId()->equals(OrganizationId::fromString($organizationId))
        ) {
            throw new QualificationTargetNotFoundException();
        }

        return $target;
    }
}
