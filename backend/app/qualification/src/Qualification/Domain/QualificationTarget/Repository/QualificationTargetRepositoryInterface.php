<?php

declare(strict_types=1);

namespace App\Qualification\Qualification\Domain\QualificationTarget\Repository;

use App\Qualification\Qualification\Domain\Qualification\ValueObject\QualificationId;
use App\Qualification\Qualification\Domain\QualificationTarget\Enum\QualificationTargetStatusEnum;
use App\Qualification\Qualification\Domain\QualificationTarget\Model\QualificationTarget;
use App\Qualification\Qualification\Domain\QualificationTarget\ValueObject\QualificationTargetId;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;

interface QualificationTargetRepositoryInterface
{
    public function save(QualificationTarget $target): void;

    /**
     * @param QualificationTarget[] $targets
     */
    public function saveAll(array $targets): void;

    public function findById(QualificationTargetId $id): ?QualificationTarget;

    /**
     * Unbounded — used internally to resolve all matching targets for business logic
     * (e.g. CreateShiftHandler). For the user-facing paginated list, see
     * getPaginatedListByQualificationId().
     *
     * @return QualificationTarget[]
     */
    public function findByQualificationId(QualificationId $qualificationId): array;

    /**
     * @param QualificationTargetStatusEnum[] $statuses
     *
     * @return QualificationTarget[]
     */
    public function findByQualificationIdAndStatuses(QualificationId $qualificationId, array $statuses): array;

    /**
     * @return ListResponse<QualificationTarget> ordered by created_at ASC
     */
    public function getPaginatedListByQualificationId(QualificationId $qualificationId, PaginationParameters $pagination, ?QualificationTargetStatusEnum $status = null): ListResponse;

    /**
     * PENDING/IN_PROGRESS (non-terminal) targets for the given owner — used to
     * cascade-cancel when the owning Qualification is cancelled.
     *
     * @return QualificationTarget[]
     */
    public function findNonTerminalByQualificationId(QualificationId $qualificationId): array;

    /**
     * @param QualificationTargetStatusEnum[] $statuses
     */
    public function countByQualificationIdAndStatuses(QualificationId $qualificationId, array $statuses): int;

    /**
     * @return array<string, int> status value => count
     */
    public function statusBreakdown(QualificationId $qualificationId): array;
}
