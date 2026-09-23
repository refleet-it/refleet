<?php

declare(strict_types=1);

namespace App\Runner\Runner\Domain\RunnerJob\Repository;

use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobKindEnum;
use App\Runner\Runner\Domain\RunnerJob\Model\RunnerJob;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerId;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;

interface RunnerJobRepositoryInterface
{
    public function save(RunnerJob $runnerJob): void;

    /**
     * @param RunnerJob[] $runnerJobs
     */
    public function saveAll(array $runnerJobs): void;

    public function findById(RunnerJobId $id): ?RunnerJob;

    /**
     * @param RunnerJobId[] $ids
     *
     * @return RunnerJob[]
     */
    public function findByIds(array $ids): array;

    /**
     * @return ListResponse<RunnerJob> ordered by created_at DESC
     */
    public function getPaginatedListByClaimedBy(OrganizationId $organizationId, string $claimedBy, PaginationParameters $pagination): ListResponse;

    /**
     * PENDING or CLAIMED jobs for the given owner — used to cascade-cancel in-flight
     * work when the owning Qualification/Shift is cancelled.
     *
     * @return RunnerJob[]
     */
    public function findNonTerminalByOwnerId(RunnerJobOwnerId $ownerId, OrganizationId $organizationId): array;

    /**
     * Atomically claims the oldest available job for the organization: a fresh PENDING
     * job, or a CLAIMED job whose lease has expired (self-healing recovery without a
     * cron — see the raw SQL in DoctrineRunnerJobRepository).
     *
     * @param RunnerJobKindEnum[]       $kinds   job kinds the runner can execute (always applied)
     * @param CriteriaModeEnum[]|null   $modes   job modes the runner can execute; null = no filter
     * @param CriteriaEngineEnum[]|null $engines static-mode engines the runner can execute; null = no filter
     */
    public function claimNext(
        OrganizationId $organizationId,
        array $kinds,
        ?array $modes,
        ?array $engines,
        string $runnerId,
        int $leaseSeconds,
    ): ?RunnerJob;

    /**
     * Among the given claimedBy names, the ones currently holding a CLAIMED job whose
     * lease has not yet expired — used to tell a WORKING runner apart from a merely
     * IDLE one (see Runner::effectiveStatus()) without coupling the Runner aggregate to
     * RunnerJob.
     *
     * @param string[] $names
     *
     * @return string[]
     */
    public function findNamesWithActiveClaimedJob(OrganizationId $organizationId, array $names): array;
}
