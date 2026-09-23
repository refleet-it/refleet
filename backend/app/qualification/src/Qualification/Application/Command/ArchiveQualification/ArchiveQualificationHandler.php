<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Command\ArchiveQualification;

use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationNotFoundException;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Archiving keeps the qualification and its targets, but moves it off the live list and out of
 * the shift picker. There is no counterpart command: archiving is one-way by design.
 */
#[AsMessageHandler]
final readonly class ArchiveQualificationHandler
{
    public function __construct(
        private QualificationRepositoryInterface $qualifications,
    ) {
    }

    public function __invoke(ArchiveQualificationCommand $command): void
    {
        $qualification = $this->qualifications->findByIdForOrganization(
            QualificationId::fromString($command->qualificationId),
            OrganizationId::fromString($command->organizationId),
        );

        if (null === $qualification) {
            throw new QualificationNotFoundException();
        }

        $qualification->archive(new \DateTimeImmutable());

        $this->qualifications->save($qualification);
    }
}
