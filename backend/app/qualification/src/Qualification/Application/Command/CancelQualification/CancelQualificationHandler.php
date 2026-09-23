<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Command\CancelQualification;

use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationNotFoundException;
use App\Qualification\Qualification\Domain\Qualification\Model\Qualification;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Qualification\Qualification\Infrastructure\Bus\OwnerJobsCancellationRequested\OwnerJobsCancellationRequestedMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Cascades to non-terminal targets and to any PENDING/CLAIMED runner jobs still in
 * flight for this qualification, so nothing keeps running against a cancelled batch.
 */
#[AsMessageHandler]
final readonly class CancelQualificationHandler
{
    public function __construct(
        private QualificationRepositoryInterface $qualifications,
        private QualificationTargetRepositoryInterface $qualificationTargets,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(CancelQualificationCommand $command): void
    {
        $qualification = $this->findOwnedQualification($command->qualificationId, $command->organizationId);

        $qualification->cancel($command->reason);

        $this->qualifications->save($qualification);

        $nonTerminalTargets = $this->qualificationTargets->findNonTerminalByQualificationId($qualification->id());

        foreach ($nonTerminalTargets as $target) {
            $target->cancel();
        }

        $this->qualificationTargets->saveAll($nonTerminalTargets);

        $this->bus->dispatch(new OwnerJobsCancellationRequestedMessage(
            ownerId: $qualification->id()->asString(),
            organizationId: $qualification->organizationId()->asString(),
        ));
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
