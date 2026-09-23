<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Application\Query\GetQualification;

use App\Qualification\Qualification\Application\Query\ListQualifications\ListQualificationsHandler;
use App\Qualification\Qualification\Domain\Qualification\Exception\QualificationNotFoundException;
use App\Qualification\Qualification\Domain\Qualification\Repository\QualificationRepositoryInterface;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\OrganizationId;
use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Repository\QualificationTargetRepositoryInterface;
use App\Shared\Domain\ValueObject\PromptSource;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GetQualificationHandler
{
    public function __construct(
        private QualificationRepositoryInterface $qualifications,
        private QualificationTargetRepositoryInterface $qualificationTargets,
    ) {
    }

    public function __invoke(GetQualificationQuery $query): QualificationDetail
    {
        $qualification = $this->qualifications->findByIdForOrganization(
            QualificationId::fromString($query->qualificationId),
            OrganizationId::fromString($query->organizationId),
        );

        if (null === $qualification) {
            throw new QualificationNotFoundException();
        }

        $breakdown = $this->qualificationTargets->statusBreakdown($qualification->id());
        $targetCount = \array_sum($breakdown);
        $criteria = $qualification->criteria();

        return new QualificationDetail(
            id: $qualification->id()->asString(),
            organizationId: $qualification->organizationId()->asString(),
            title: $qualification->title(),
            description: $qualification->description(),
            createdBy: $qualification->createdBy()->asString(),
            status: $qualification->status()->value,
            qualificationMode: $criteria->mode()->value,
            qualificationEngine: $criteria->engine()?->value,
            qualificationPrompt: $criteria->prompt(),
            qualificationModel: $criteria->model(),
            qualificationRules: $criteria->rules(),
            qualificationSources: \array_map(static fn (PromptSource $source): array => $source->toArray(), $criteria->sources()),
            cancelReason: $qualification->cancelReason(),
            targetCount: $targetCount,
            statusBreakdown: $breakdown,
            progressPercent: ListQualificationsHandler::progressPercent($breakdown, $targetCount),
            createdAt: $qualification->createdAt()->format('c'),
            startedAt: $qualification->startedAt()?->format('c'),
            completedAt: $qualification->completedAt()?->format('c'),
            cancelledAt: $qualification->cancelledAt()?->format('c'),
            archivedAt: $qualification->archivedAt()?->format('c'),
        );
    }
}
