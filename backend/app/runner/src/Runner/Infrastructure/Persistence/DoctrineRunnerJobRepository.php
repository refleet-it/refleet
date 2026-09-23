<?php

declare(strict_types=1);

namespace App\Runner\Runner\Infrastructure\Persistence;

use App\Runner\Runner\Domain\Runner\ValueObject\OrganizationId;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobKindEnum;
use App\Runner\Runner\Domain\RunnerJob\Enum\RunnerJobStatusEnum;
use App\Runner\Runner\Domain\RunnerJob\Model\RunnerJob;
use App\Runner\Runner\Domain\RunnerJob\Repository\RunnerJobRepositoryInterface;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobId;
use App\Runner\Runner\Domain\RunnerJob\ValueObject\RunnerJobOwnerId;
use App\Shared\Domain\Enum\CriteriaEngineEnum;
use App\Shared\Domain\Enum\CriteriaModeEnum;
use App\Shared\Domain\ValueObject\ListResponse;
use App\Shared\Domain\ValueObject\Pagination\PaginationParameters;
use App\Shared\Infrastructure\Doctrine\QueryBuilderPaginator;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineRunnerJobRepository implements RunnerJobRepositoryInterface
{
    /**
     * created_at has second precision, so jobs enqueued in one burst tie on it; the
     * UUIDv7 id breaks the tie in mint order, keeping the queue a real FIFO.
     */
    private const string CLAIM_NEXT_SQL = <<<'SQL'
        UPDATE runner.runner_jobs
        SET status = 'claimed', claimed_by = :runnerId, claimed_at = :now,
            lease_expires_at = :leaseExpiresAt, attempt_count = attempt_count + 1
        WHERE id = (
            SELECT id FROM runner.runner_jobs
            WHERE organization_id = :organizationId
              AND kind IN (:kinds)
              AND (:hasModeFilter = false OR mode IN (:modes))
              AND (:hasEngineFilter = false OR engine IS NULL OR engine IN (:engines))
              AND (status = 'pending' OR (status = 'claimed' AND lease_expires_at < :now))
            ORDER BY created_at ASC, id ASC
            FOR UPDATE SKIP LOCKED
            LIMIT 1
        )
        RETURNING id
        SQL;

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    #[\Override]
    public function save(RunnerJob $runnerJob): void
    {
        $this->em->persist($runnerJob);
        $this->em->flush();
    }

    #[\Override]
    public function saveAll(array $runnerJobs): void
    {
        foreach ($runnerJobs as $runnerJob) {
            $this->em->persist($runnerJob);
        }

        $this->em->flush();
    }

    #[\Override]
    public function findById(RunnerJobId $id): ?RunnerJob
    {
        return $this->em->find(RunnerJob::class, $id->asString());
    }

    #[\Override]
    public function findByIds(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $stringIds = \array_map(static fn (RunnerJobId $id): string => $id->asString(), $ids);

        /* @var RunnerJob[] */
        return $this->em->getRepository(RunnerJob::class)->findBy(['id' => $stringIds]);
    }

    #[\Override]
    public function getPaginatedListByClaimedBy(OrganizationId $organizationId, string $claimedBy, PaginationParameters $pagination): ListResponse
    {
        $qb = $this->em
            ->getRepository(RunnerJob::class)
            ->createQueryBuilder('j')
            ->where('j.organizationId = :organizationId')
            ->andWhere('j.claimedBy = :claimedBy')
            ->setParameter('organizationId', $organizationId->asString())
            ->setParameter('claimedBy', $claimedBy)
            ->orderBy('j.createdAt', 'DESC');

        /** @var RunnerJob[] $items */
        [$items, $total] = QueryBuilderPaginator::paginate($qb, $pagination);

        return ListResponse::create($items, $total, $pagination);
    }

    #[\Override]
    public function findNonTerminalByOwnerId(RunnerJobOwnerId $ownerId, OrganizationId $organizationId): array
    {
        return $this->em->getRepository(RunnerJob::class)->findBy([
            'ownerId' => $ownerId->asString(),
            'organizationId' => $organizationId->asString(),
            'status' => [RunnerJobStatusEnum::PENDING->value, RunnerJobStatusEnum::CLAIMED->value],
        ]);
    }

    #[\Override]
    public function findNamesWithActiveClaimedJob(OrganizationId $organizationId, array $names): array
    {
        if ([] === $names) {
            return [];
        }

        /** @var string[] $rows */
        $rows = $this->em
            ->getRepository(RunnerJob::class)
            ->createQueryBuilder('j')
            ->select('DISTINCT j.claimedBy')
            ->where('j.organizationId = :organizationId')
            ->andWhere('j.status = :status')
            ->andWhere('j.claimedBy IN (:names)')
            ->andWhere('j.leaseExpiresAt >= :now')
            ->setParameter('organizationId', $organizationId->asString())
            ->setParameter('status', RunnerJobStatusEnum::CLAIMED)
            ->setParameter('names', $names)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleColumnResult();

        return $rows;
    }

    /**
     * Atomic claim under concurrent runners: ORM cannot express `FOR UPDATE SKIP LOCKED`
     * combined with `ORDER BY ... LIMIT 1` inside a single atomic UPDATE, so this goes
     * through the native DBAL connection. The UPDATE also doubles as the self-healing
     * "reclaim" path: a job whose lease has expired (`status = 'claimed' AND
     * lease_expires_at < now`) is picked up exactly like a fresh 'pending' job.
     *
     * After the raw UPDATE, the entity is loaded (or refreshed, if already in the
     * identity map) so the in-memory aggregate reflects the row the UPDATE just wrote.
     *
     * @param RunnerJobKindEnum[]       $kinds   job kinds the runner can execute (always applied)
     * @param CriteriaModeEnum[]|null   $modes   job modes the runner can execute; null = no filter
     * @param CriteriaEngineEnum[]|null $engines engines the runner can execute (static-mode engines and/or AI-mode agents); null = no filter
     */
    #[\Override]
    public function claimNext(
        OrganizationId $organizationId,
        array $kinds,
        ?array $modes,
        ?array $engines,
        string $runnerId,
        int $leaseSeconds,
    ): ?RunnerJob {
        $now = new \DateTimeImmutable();
        $leaseExpiresAt = $now->modify(\sprintf('+%d seconds', $leaseSeconds));

        $result = $this->em->getConnection()->executeQuery(
            self::CLAIM_NEXT_SQL,
            $this->claimNextParams($organizationId, $kinds, $modes, $engines, $runnerId, $now, $leaseExpiresAt),
            [
                'kinds' => ArrayParameterType::STRING,
                'modes' => ArrayParameterType::STRING,
                'engines' => ArrayParameterType::STRING,
                'hasModeFilter' => ParameterType::BOOLEAN,
                'hasEngineFilter' => ParameterType::BOOLEAN,
            ],
        );

        $id = $result->fetchOne();

        if (false === $id) {
            return null;
        }

        /** @var RunnerJob|null $job */
        $job = $this->em->find(RunnerJob::class, $id);

        if (null !== $job) {
            $this->em->refresh($job);
        }

        return $job;
    }

    /**
     * @param RunnerJobKindEnum[]       $kinds
     * @param CriteriaModeEnum[]|null   $modes
     * @param CriteriaEngineEnum[]|null $engines
     *
     * @return array{organizationId: string, kinds: string[], modes: string[], engines: string[], hasModeFilter: bool, hasEngineFilter: bool, now: string, leaseExpiresAt: string, runnerId: string}
     */
    private function claimNextParams(
        OrganizationId $organizationId,
        array $kinds,
        ?array $modes,
        ?array $engines,
        string $runnerId,
        \DateTimeImmutable $now,
        \DateTimeImmutable $leaseExpiresAt,
    ): array {
        return [
            'organizationId' => $organizationId->asString(),
            'kinds' => \array_map(static fn (RunnerJobKindEnum $kind): string => $kind->value, $kinds),
            'modes' => null !== $modes
                ? \array_map(static fn (CriteriaModeEnum $mode): string => $mode->value, $modes)
                : [],
            'engines' => null !== $engines
                ? \array_map(static fn (CriteriaEngineEnum $engine): string => $engine->value, $engines)
                : [],
            'hasModeFilter' => null !== $modes,
            'hasEngineFilter' => null !== $engines,
            'now' => $now->format('Y-m-d H:i:s'),
            'leaseExpiresAt' => $leaseExpiresAt->format('Y-m-d H:i:s'),
            'runnerId' => $runnerId,
        ];
    }
}
