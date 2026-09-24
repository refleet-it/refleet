<?php

declare(strict_types=1);

namespace App\Shift\Shift\Domain\ShiftTarget\Repository;

use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shift\Shift\Domain\Shift\ValueObject\ShiftId;
use App\Shift\Shift\Domain\ShiftTarget\Enum\ShiftTargetStatusEnum;
use App\Shift\Shift\Domain\ShiftTarget\Model\ShiftTarget;
use App\Shift\Shift\Domain\ShiftTarget\ValueObject\ShiftTargetId;

interface ShiftTargetRepositoryInterface
{
    public function save(ShiftTarget $target): void;

    /**
     * @param ShiftTarget[] $targets
     */
    public function saveAll(array $targets): void;

    public function findById(ShiftTargetId $id): ?ShiftTarget;

    /**
     * Targets waiting on a merge request that has not been checked since $checkedBefore,
     * least recently checked first. The limit caps a single polling pass, so a fleet with
     * thousands of open merge requests spreads its GitLab calls over several passes
     * rather than bursting them into one.
     *
     * @return ShiftTarget[]
     */
    public function findOpenMergeRequestsToCheck(\DateTimeImmutable $checkedBefore, int $limit): array;

    /**
     * @return ListResponse<ShiftTarget> ordered by created_at ASC
     */
    public function getPaginatedListByShiftId(ShiftId $shiftId, PaginationParameters $pagination, ?ShiftTargetStatusEnum $status = null): ListResponse;

    /**
     * @param ShiftTargetStatusEnum[] $statuses
     *
     * @return ShiftTarget[]
     */
    public function findByShiftIdAndStatuses(ShiftId $shiftId, array $statuses): array;

    /**
     * PENDING_CHANGE or in-flight targets for the given shift — used to cascade-cancel
     * when the owning Shift is cancelled.
     *
     * @return ShiftTarget[]
     */
    public function findNonTerminalByShiftId(ShiftId $shiftId): array;

    /**
     * @param ShiftTargetStatusEnum[] $statuses
     */
    public function countByShiftIdAndStatuses(ShiftId $shiftId, array $statuses): int;

    /**
     * @return array<string, int> status value => count
     */
    public function statusBreakdown(ShiftId $shiftId): array;
}
